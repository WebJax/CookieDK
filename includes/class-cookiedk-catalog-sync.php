<?php
/**
 * Remote-sync af cookie-kataloget med GitHub.
 *
 * Henter cookies.json og providers.json og pusher AI-genererede
 * cookie-poster tilbage til GitHub-kataloget.
 *
 * @package CookieDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klasse CookieDK_Catalog_Sync.
 */
class CookieDK_Catalog_Sync {

	/**
	 * Option-nøgle for sync-metadata.
	 *
	 * @var string
	 */
	const META_OPTION = 'cookiedk_catalog_meta';

	/**
	 * Maksimal størrelse for en katalogfil i bytes.
	 *
	 * @var int
	 */
	const MAX_BYTES = 2097152;

	/**
	 * Registrerer cron-hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'cookiedk_daily_cron', array( $this, 'maybe_sync' ) );
	}

	/**
	 * Synkroniserer kataloget hvis auto-sync er slået til.
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_sync() {
		$settings = $this->get_plugin_settings();
		if ( empty( $settings['catalog_auto_sync'] ) ) {
			return false;
		}

		return $this->sync();
	}

	/**
	 * Henter cookies.json og providers.json og gemmer et lokalt snapshot.
	 *
	 * @return true|WP_Error
	 */
	public function sync() {
		$urls = $this->get_catalog_urls();
		$meta = $this->get_meta();

		$cookies = $this->download_catalog_file(
			$urls['cookies'],
			isset( $meta['cookies_etag'] ) ? (string) $meta['cookies_etag'] : ''
		);
		if ( is_wp_error( $cookies ) ) {
			$this->update_meta( array( 'last_error' => $cookies->get_error_message() ) );
			return $cookies;
		}

		$providers = $this->download_catalog_file(
			$urls['providers'],
			isset( $meta['providers_etag'] ) ? (string) $meta['providers_etag'] : ''
		);
		if ( is_wp_error( $providers ) ) {
			$this->update_meta( array( 'last_error' => $providers->get_error_message() ) );
			return $providers;
		}

		$dir = $this->get_catalog_dir();
		if ( is_wp_error( $dir ) ) {
			$this->update_meta( array( 'last_error' => $dir->get_error_message() ) );
			return $dir;
		}

		if ( ! empty( $cookies['body'] ) ) {
			$validated = $this->validate_catalog_json( $cookies['body'], 'cookies' );
			if ( is_wp_error( $validated ) ) {
				$this->update_meta( array( 'last_error' => $validated->get_error_message() ) );
				return $validated;
			}
			$written = $this->write_snapshot( $dir . '/cookies.json', $cookies['body'] );
			if ( is_wp_error( $written ) ) {
				$this->update_meta( array( 'last_error' => $written->get_error_message() ) );
				return $written;
			}
			$meta['cookies_etag']   = $cookies['etag'];
			$meta['cookies_sha256'] = hash( 'sha256', $cookies['body'] );
		}

		if ( ! empty( $providers['body'] ) ) {
			$validated = $this->validate_catalog_json( $providers['body'], 'providers' );
			if ( is_wp_error( $validated ) ) {
				$this->update_meta( array( 'last_error' => $validated->get_error_message() ) );
				return $validated;
			}
			$written = $this->write_snapshot( $dir . '/providers.json', $providers['body'] );
			if ( is_wp_error( $written ) ) {
				$this->update_meta( array( 'last_error' => $written->get_error_message() ) );
				return $written;
			}
			$meta['providers_etag']   = $providers['etag'];
			$meta['providers_sha256'] = hash( 'sha256', $providers['body'] );
		}

		$meta['synced_at']  = current_time( 'mysql' );
		$meta['last_error'] = '';
		$this->update_meta( $meta );

		if ( class_exists( 'CookieDK_Cookie_Database' ) ) {
			CookieDK_Cookie_Database::reset_cache();
		}

		$this->reclassify_unlocked_cookies();

		return true;
	}

	/**
	 * Tilføjer en cookie-post til GitHub-kataloget og opdaterer det lokale snapshot.
	 *
	 * Eksisterende nøgler i GitHub-kataloget overskrives ikke.
	 *
	 * @param array<string, mixed> $entry Cookie-post i cookies.json-skema.
	 * @return true|WP_Error
	 */
	public function push_cookie_entry( array $entry ) {
		if ( empty( $entry['name'] ) ) {
			return new WP_Error( 'cookiedk_push_invalid', __( 'Cookie-posten mangler et navn.', 'cookiedk' ) );
		}

		$name = (string) $entry['name'];
		if ( ! $this->is_safe_cookie_key( $name ) ) {
			return new WP_Error( 'cookiedk_push_invalid', __( 'Ugyldigt cookie-navn.', 'cookiedk' ) );
		}

		$token = $this->get_github_token();
		if ( '' === $token ) {
			return new WP_Error(
				'cookiedk_push_no_token',
				__( 'GitHub-token mangler. Tilføj COOKIEDK_GITHUB_TOKEN eller et token under Indstillinger.', 'cookiedk' )
			);
		}

		$repo = $this->get_github_repo();
		$path = $this->get_github_cookies_path();
		$ref  = $this->get_github_branch();

		$remote = $this->github_get_file( $repo, $path, $ref, $token );
		if ( is_wp_error( $remote ) ) {
			$this->update_meta(
				array(
					'last_push_error'  => $remote->get_error_message(),
					'last_push_cookie' => $name,
				)
			);
			return $remote;
		}

		$catalog = json_decode( $remote['content'], true );
		if ( ! is_array( $catalog ) ) {
			$error = new WP_Error( 'cookiedk_push_decode', __( 'GitHub-kataloget kunne ikke læses.', 'cookiedk' ) );
			$this->update_meta(
				array(
					'last_push_error'  => $error->get_error_message(),
					'last_push_cookie' => $name,
				)
			);
			return $error;
		}

		if ( isset( $catalog[ $name ] ) && is_array( $catalog[ $name ] ) ) {
			$this->write_snapshot_json( 'cookies.json', $catalog );
			$this->update_meta(
				array(
					'last_push_at'     => current_time( 'mysql' ),
					'last_push_cookie' => $name,
					'last_push_error'  => '',
				)
			);
			if ( class_exists( 'CookieDK_Cookie_Database' ) ) {
				CookieDK_Cookie_Database::reset_cache();
			}
			return true;
		}

		$catalog[ $name ] = $entry;
		$encoded          = $this->encode_catalog_json( $catalog );
		if ( '' === $encoded ) {
			return new WP_Error( 'cookiedk_push_encode', __( 'Kataloget kunne ikke kodes som JSON.', 'cookiedk' ) );
		}

		$put = $this->github_put_file(
			$repo,
			$path,
			$ref,
			$token,
			$remote['sha'],
			$encoded,
			sprintf(
				/* translators: %s: Cookie name. */
				__( 'Add cookie %s from CookieDK AI catalog sync', 'cookiedk' ),
				$name
			)
		);

		if ( is_wp_error( $put ) ) {
			$this->update_meta(
				array(
					'last_push_error'  => $put->get_error_message(),
					'last_push_cookie' => $name,
				)
			);
			return $put;
		}

		$this->write_snapshot_json( 'cookies.json', $catalog );
		$this->update_meta(
			array(
				'last_push_at'     => current_time( 'mysql' ),
				'last_push_cookie' => $name,
				'last_push_error'  => '',
				'synced_at'        => current_time( 'mysql' ),
				'cookies_sha256'   => hash( 'sha256', $encoded ),
			)
		);

		if ( class_exists( 'CookieDK_Cookie_Database' ) ) {
			CookieDK_Cookie_Database::reset_cache();
		}

		$this->reclassify_unlocked_cookies();

		return true;
	}

	/**
	 * Returnerer sti til snapshot-fil hvis den findes og er læsbar.
	 *
	 * @param string $filename cookies.json eller providers.json.
	 * @return string
	 */
	public function get_snapshot_path( $filename ) {
		$filename = (string) $filename;
		if ( ! in_array( $filename, array( 'cookies.json', 'providers.json' ), true ) ) {
			return '';
		}

		$dir = $this->get_catalog_dir( false );
		if ( is_wp_error( $dir ) || '' === $dir ) {
			return '';
		}

		$path = $dir . '/' . $filename;
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Returnerer sync-metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function get_meta() {
		$stored = get_option( self::META_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args(
			$stored,
			array(
				'synced_at'        => '',
				'cookies_etag'     => '',
				'providers_etag'   => '',
				'cookies_sha256'   => '',
				'providers_sha256' => '',
				'last_error'       => '',
				'last_push_at'     => '',
				'last_push_cookie' => '',
				'last_push_error'  => '',
			)
		);
	}

	/**
	 * Standard-URL'er til katalogfilerne.
	 *
	 * @return array{cookies:string,providers:string}
	 */
	public function get_catalog_urls() {
		$defaults = array(
			'cookies'   => 'https://raw.githubusercontent.com/WebJax/CookieDK/main/database/cookies.json',
			'providers' => 'https://raw.githubusercontent.com/WebJax/CookieDK/main/database/providers.json',
		);

		$urls = apply_filters( 'cookiedk_catalog_urls', $defaults );
		if ( ! is_array( $urls ) ) {
			return $defaults;
		}

		return array(
			'cookies'   => isset( $urls['cookies'] ) ? (string) $urls['cookies'] : $defaults['cookies'],
			'providers' => isset( $urls['providers'] ) ? (string) $urls['providers'] : $defaults['providers'],
		);
	}

	/**
	 * Reklassificerer ulåste cookies ud fra det aktuelle katalog.
	 *
	 * @return void
	 */
	public function reclassify_unlocked_cookies() {
		if ( ! class_exists( 'CookieDK_Cookie_Storage' ) || ! class_exists( 'CookieDK_Cookie_Detector' ) ) {
			return;
		}

		$storage  = new CookieDK_Cookie_Storage();
		$detector = new CookieDK_Cookie_Detector();
		$cookies  = $storage->get_all_cookies();

		foreach ( $cookies as $cookie ) {
			if ( empty( $cookie->name ) || ! empty( $cookie->locked ) ) {
				continue;
			}

			$origin = isset( $cookie->origin ) ? (string) $cookie->origin : 'unknown';
			if ( ! in_array( $origin, array( 'unknown', 'ai' ), true ) ) {
				continue;
			}

			$meta = $detector->classify_cookie( (string) $cookie->name );
			if ( empty( $meta['origin'] ) || 'unknown' === $meta['origin'] ) {
				continue;
			}

			$storage->save_cookie(
				array_merge(
					array(
						'name'   => $cookie->name,
						'source' => isset( $cookie->source ) ? $cookie->source : 'server',
					),
					$meta
				)
			);
		}
	}

	/**
	 * Henter en katalogfil. Tom body betyder HTTP 304.
	 *
	 * @param string $url  Remote URL.
	 * @param string $etag Forrige ETag.
	 * @return array{body:string,etag:string}|WP_Error
	 */
	private function download_catalog_file( $url, $etag = '' ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url || ! $this->is_allowed_url( $url ) ) {
			return new WP_Error( 'cookiedk_catalog_url', __( 'Katalog-URL er ikke tilladt.', 'cookiedk' ) );
		}

		$args = array(
			'timeout'     => 15,
			'headers'     => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'CookieDK/' . ( defined( 'COOKIEDK_VERSION' ) ? COOKIEDK_VERSION : '1.0' ),
			),
			'sslverify'   => true,
			'redirection' => 2,
		);
		if ( '' !== $etag ) {
			$args['headers']['If-None-Match'] = $etag;
		}

		$response = $this->remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 304 === $code ) {
			return array(
				'body' => '',
				'etag' => $etag,
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'cookiedk_catalog_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Kataloget kunne ikke hentes (HTTP %d).', 'cookiedk' ),
					$code
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BYTES ) {
			return new WP_Error( 'cookiedk_catalog_size', __( 'Katalogfilen er for stor.', 'cookiedk' ) );
		}

		return array(
			'body' => $body,
			'etag' => (string) wp_remote_retrieve_header( $response, 'etag' ),
		);
	}

	/**
	 * Validerer en katalog-JSON-streng.
	 *
	 * @param string $raw  JSON.
	 * @param string $kind cookies eller providers.
	 * @return true|WP_Error
	 */
	public function validate_catalog_json( $raw, $kind = 'cookies' ) {
		$decoded = json_decode( (string) $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new WP_Error( 'cookiedk_catalog_json', __( 'Katalogfilen er ikke gyldig JSON.', 'cookiedk' ) );
		}

		if ( array_values( $decoded ) === $decoded ) {
			return new WP_Error( 'cookiedk_catalog_shape', __( 'Katalogfilen skal være et objekt, ikke en liste.', 'cookiedk' ) );
		}

		if ( count( $decoded ) > 10000 ) {
			return new WP_Error( 'cookiedk_catalog_count', __( 'Katalogfilen har for mange poster.', 'cookiedk' ) );
		}

		foreach ( $decoded as $key => $entry ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $entry ) ) {
				return new WP_Error( 'cookiedk_catalog_entry', __( 'Katalogfilen indeholder en ugyldig post.', 'cookiedk' ) );
			}
			if ( 'cookies' === $kind && ! $this->is_safe_cookie_key( $key ) ) {
				return new WP_Error( 'cookiedk_catalog_entry', __( 'Katalogfilen indeholder et ugyldigt cookie-navn.', 'cookiedk' ) );
			}
		}

		return true;
	}

	/**
	 * Returnerer katalog-mappen i uploads.
	 *
	 * @param bool $create Opret mappen hvis den mangler.
	 * @return string|WP_Error
	 */
	public function get_catalog_dir( $create = true ) {
		$filtered = apply_filters( 'cookiedk_catalog_dir', '' );
		if ( is_string( $filtered ) && '' !== $filtered ) {
			$dir = untrailingslashit( $filtered );
			if ( $create && ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'cookiedk_catalog_dir', __( 'Katalog-mappen kunne ikke oprettes.', 'cookiedk' ) );
			}
			return $dir;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'cookiedk_catalog_dir', (string) $uploads['error'] );
		}

		$dir = untrailingslashit( (string) $uploads['basedir'] ) . '/cookiedk';
		if ( $create && ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'cookiedk_catalog_dir', __( 'Katalog-mappen kunne ikke oprettes.', 'cookiedk' ) );
		}

		return $dir;
	}

	/**
	 * Skriver en snapshot-fil til disk.
	 *
	 * @param string $path Absolut sti.
	 * @param string $body Filindhold.
	 * @return true|WP_Error
	 */
	private function write_snapshot( $path, $body ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local catalog snapshot.
		$ok = file_put_contents( $path, $body );
		if ( false === $ok ) {
			return new WP_Error( 'cookiedk_catalog_write', __( 'Katalog-snapshot kunne ikke gemmes.', 'cookiedk' ) );
		}

		return true;
	}

	/**
	 * Skriver dekodet katalog-JSON som snapshot.
	 *
	 * @param string               $filename Filnavn i katalog-mappen.
	 * @param array<string, mixed> $data     Dekodet JSON.
	 * @return true|WP_Error
	 */
	private function write_snapshot_json( $filename, array $data ) {
		$dir = $this->get_catalog_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$encoded = $this->encode_catalog_json( $data );
		if ( '' === $encoded ) {
			return new WP_Error( 'cookiedk_catalog_encode', __( 'Kataloget kunne ikke kodes som JSON.', 'cookiedk' ) );
		}

		return $this->write_snapshot( $dir . '/' . $filename, $encoded );
	}

	/**
	 * Pretty-printer JSON med 2-space indent, som bundled cookies.json.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return string
	 */
	public function encode_catalog_json( array $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		$json = preg_replace_callback(
			'/^(?:    )+/m',
			static function ( $matches ) {
				return str_repeat( '  ', (int) ( strlen( $matches[0] ) / 4 ) );
			},
			$json
		);

		return is_string( $json ) ? $json . "\n" : '';
	}

	/**
	 * Henter en fil via GitHub Contents API.
	 *
	 * @param string $repo  owner/name.
	 * @param string $path  Filsti.
	 * @param string $ref   Branch.
	 * @param string $token GitHub-token.
	 * @return array{content:string,sha:string}|WP_Error
	 */
	private function github_get_file( $repo, $path, $ref, $token ) {
		$url = sprintf(
			'https://api.github.com/repos/%s/contents/%s?ref=%s',
			$repo,
			implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) ) ),
			rawurlencode( $ref )
		);

		$response = $this->remote_request(
			$url,
			array(
				'timeout' => 15,
				'headers' => $this->github_headers( $token ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'cookiedk_github_get',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'GitHub-kataloget kunne ikke hentes (HTTP %d).', 'cookiedk' ),
					$code
				)
			);
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['content'] ) || empty( $decoded['sha'] ) ) {
			return new WP_Error( 'cookiedk_github_get', __( 'GitHub-svaret var ugyldigt.', 'cookiedk' ) );
		}

		$content = base64_decode( str_replace( "\n", '', (string) $decoded['content'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- GitHub Contents API encoding.
		if ( false === $content ) {
			return new WP_Error( 'cookiedk_github_get', __( 'GitHub-filen kunne ikke dekodes.', 'cookiedk' ) );
		}

		return array(
			'content' => $content,
			'sha'     => (string) $decoded['sha'],
		);
	}

	/**
	 * Opdaterer en fil via GitHub Contents API.
	 *
	 * @param string $repo    owner/name.
	 * @param string $path    Filsti.
	 * @param string $ref     Branch.
	 * @param string $token   Token.
	 * @param string $sha     Blob-SHA.
	 * @param string $content Filindhold.
	 * @param string $message Commit-besked.
	 * @return true|WP_Error
	 */
	private function github_put_file( $repo, $path, $ref, $token, $sha, $content, $message ) {
		$url = sprintf(
			'https://api.github.com/repos/%s/contents/%s',
			$repo,
			implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $path, '/' ) ) ) )
		);

		$body = wp_json_encode(
			array(
				'message' => $message,
				'content' => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- GitHub Contents API encoding.
				'sha'     => $sha,
				'branch'  => $ref,
			)
		);

		$response = $this->remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 20,
				'headers' => $this->github_headers( $token ),
				'body'    => is_string( $body ) ? $body : '',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'cookiedk_github_put',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'GitHub-kataloget kunne ikke opdateres (HTTP %d).', 'cookiedk' ),
					$code
				)
			);
		}

		return true;
	}

	/**
	 * Headers til GitHub API.
	 *
	 * @param string $token Token.
	 * @return array<string, string>
	 */
	private function github_headers( $token ) {
		return array(
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . $token,
			'User-Agent'           => 'CookieDK/' . ( defined( 'COOKIEDK_VERSION' ) ? COOKIEDK_VERSION : '1.0' ),
			'X-GitHub-Api-Version' => '2022-11-28',
			'Content-Type'         => 'application/json',
		);
	}

	/**
	 * Udfører HTTP-request, med test-filter.
	 *
	 * @param string               $url  URL.
	 * @param array<string, mixed> $args wp_remote_request-args.
	 * @return array|WP_Error
	 */
	private function remote_request( $url, array $args ) {
		$pre = apply_filters( 'cookiedk_pre_remote_request', null, $url, $args );
		if ( null !== $pre ) {
			return $pre;
		}

		return wp_remote_request( $url, $args );
	}

	/**
	 * Tjekker om URL'en er HTTPS og på allowlisten.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_allowed_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}

		$host    = strtolower( (string) $parts['host'] );
		$allowed = apply_filters(
			'cookiedk_catalog_allowed_hosts',
			array(
				'raw.githubusercontent.com',
				'api.github.com',
			)
		);
		if ( ! is_array( $allowed ) ) {
			$allowed = array();
		}

		return in_array( $host, $allowed, true );
	}

	/**
	 * Tillader kun sikre cookie-nøgler i kataloget.
	 *
	 * @param string $key Cookie-nøgle.
	 * @return bool
	 */
	public function is_safe_cookie_key( $key ) {
		$key = (string) $key;
		if ( '' === $key || strlen( $key ) > 255 ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Za-z0-9._*\-]+$/', $key );
	}

	/**
	 * Returnerer GitHub-token fra konstant, indstilling eller filter.
	 *
	 * @return string
	 */
	public function get_github_token() {
		$token = '';
		if ( defined( 'COOKIEDK_GITHUB_TOKEN' ) && is_string( COOKIEDK_GITHUB_TOKEN ) ) {
			$token = COOKIEDK_GITHUB_TOKEN;
		}

		$settings = $this->get_plugin_settings();
		if ( '' === $token && ! empty( $settings['github_token'] ) ) {
			$token = (string) $settings['github_token'];
		}

		$token = apply_filters( 'cookiedk_github_token', $token );
		return is_string( $token ) ? trim( $token ) : '';
	}

	/**
	 * Returnerer GitHub-repo i formatet ejer/navn.
	 *
	 * @return string
	 */
	public function get_github_repo() {
		$settings = $this->get_plugin_settings();
		$repo     = isset( $settings['github_repo'] ) ? (string) $settings['github_repo'] : 'WebJax/CookieDK';
		$repo     = apply_filters( 'cookiedk_github_repo', $repo );
		if ( ! is_string( $repo ) || ! preg_match( '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repo ) ) {
			return 'WebJax/CookieDK';
		}

		return $repo;
	}

	/**
	 * Returnerer GitHub-branch til sync og push.
	 *
	 * @return string
	 */
	public function get_github_branch() {
		$settings = $this->get_plugin_settings();
		$branch   = isset( $settings['github_branch'] ) ? (string) $settings['github_branch'] : 'main';
		$branch   = apply_filters( 'cookiedk_github_branch', $branch );
		if ( ! is_string( $branch ) || ! preg_match( '/^[A-Za-z0-9._\/\-]+$/', $branch ) ) {
			return 'main';
		}

		return $branch;
	}

	/**
	 * Returnerer stien til cookies.json i GitHub-repoet.
	 *
	 * @return string
	 */
	public function get_github_cookies_path() {
		$path = apply_filters( 'cookiedk_github_cookies_path', 'database/cookies.json' );
		if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, '..' ) ) {
			return 'database/cookies.json';
		}

		return ltrim( $path, '/' );
	}

	/**
	 * Opdaterer sync-metadata.
	 *
	 * @param array<string, mixed> $changes Ændringer.
	 * @return void
	 */
	private function update_meta( array $changes ) {
		update_option( self::META_OPTION, array_merge( $this->get_meta(), $changes ) );
	}

	/**
	 * Returnerer plugin-indstillinger med katalog-defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_plugin_settings() {
		$stored = get_option( 'cookiedk_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args(
			$stored,
			array(
				'catalog_auto_sync' => true,
				'github_token'      => '',
				'github_repo'       => 'WebJax/CookieDK',
				'github_branch'     => 'main',
			)
		);
	}
}
