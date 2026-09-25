<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin client for the Anthropic Messages API. Two jobs only:
 *  - draft_message(): write a short, natural WhatsApp text asking for a quote
 *  - parse_quote_pdf(): read an uploaded PDF quote and return structured items/prices
 */
class Cpm_Anthropic {

	const API_URL = 'https://api.anthropic.com/v1/messages';

	public static function get_settings() {
		return wp_parse_args( get_option( 'cpm_settings', array() ), array(
			'anthropic_api_key' => '',
			'anthropic_model'   => 'claude-sonnet-5',
			'notify_email'      => get_option( 'admin_email' ),
		) );
	}

	private static function api_key() {
		$s = self::get_settings();
		return trim( $s['anthropic_api_key'] );
	}

	private static function model() {
		$s = self::get_settings();
		return $s['anthropic_model'] ? $s['anthropic_model'] : 'claude-sonnet-5';
	}

	/**
	 * @param array $content Anthropic "content" blocks array for the single user message.
	 * @return string|WP_Error Raw text of the first text block in the reply.
	 */
	private static function request( $content, $max_tokens = 600 ) {
		$key = self::api_key();
		if ( ! $key ) {
			return new WP_Error( 'cpm_no_key', 'Chave da API da Anthropic não configurada. Vá em Monitor de Preços → Configurações.' );
		}

		$body = array(
			'model'      => self::model(),
			'max_tokens' => $max_tokens,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $content ),
			),
		);

		$response = wp_remote_post( self::API_URL, array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Erro desconhecido na API da Anthropic (HTTP ' . $code . ').';
			return new WP_Error( 'cpm_api_error', $msg );
		}

		if ( empty( $data['content'][0]['text'] ) ) {
			return new WP_Error( 'cpm_empty_reply', 'A API não retornou texto na resposta.' );
		}

		return $data['content'][0]['text'];
	}

	/**
	 * @param object $persona     Row from cpm_personas (name, voice).
	 * @param array  $items       List of ['name' => .., 'spec' => .., 'unit' => .., 'quantity' => ..].
	 * @return string|WP_Error
	 */
	public static function draft_message( $persona, $items ) {
		$lines = array();
		foreach ( $items as $it ) {
			$lines[] = sprintf( '- %s%s: %s %s', $it['name'], $it['spec'] ? ' (' . $it['spec'] . ')' : '', $it['quantity'], $it['unit'] );
		}
		$items_text = implode( "\n", $lines );

		$prompt = "Escreva uma única mensagem de WhatsApp em português do Brasil, curta e natural, como se fosse "
			. "um cliente comum pedindo um orçamento de material de construção. "
			. "Persona a interpretar: \"{$persona->name}\" — jeito de falar/contexto: {$persona->voice}. "
			. "Itens e quantidades a pedir:\n{$items_text}\n\n"
			. "Regras: só texto (a pessoa vai copiar e colar no WhatsApp, sem áudio). "
			. "Tom informal, como mensagem real de WhatsApp, sem parecer formulário nem e-mail corporativo. "
			. "Não mencione preço-alvo, não mencione ser empresa, não use saudação de call center. "
			. "Responda APENAS com o texto da mensagem, sem aspas, sem explicações antes ou depois.";

		return self::request( array( array( 'type' => 'text', 'text' => $prompt ) ), 400 );
	}

	/**
	 * @param string $file_path Absolute path to the uploaded PDF.
	 * @return array|WP_Error {supplier, quote_date, items:[{name, quantity, unit, unit_price, total_price}]}
	 */
	public static function parse_quote_pdf( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'cpm_no_file', 'Arquivo PDF não encontrado.' );
		}

		$base64 = base64_encode( file_get_contents( $file_path ) );

		$instructions = "Este PDF é um orçamento de material de construção enviado por um fornecedor. "
			. "Extraia os dados e responda ESTRITAMENTE com um JSON válido (sem markdown, sem comentários), no formato:\n"
			. '{"supplier": "nome do fornecedor ou null", "quote_date": "AAAA-MM-DD ou null", '
			. '"items": [{"name": "nome do item como está no documento", "quantity": numero ou null, '
			. "\"unit\": \"unidade (m, kg, un, etc) ou null\", \"unit_price\": numero ou null, \"total_price\": numero ou null}]}\n"
			. "Use ponto como separador decimal no JSON (ex: 12.50), mesmo que o PDF use vírgula. "
			. "Se não souber um campo, use null. Não invente itens que não estão no documento.";

		$content = array(
			array(
				'type'   => 'document',
				'source' => array(
					'type'       => 'base64',
					'media_type' => 'application/pdf',
					'data'       => $base64,
				),
			),
			array( 'type' => 'text', 'text' => $instructions ),
		);

		$text = self::request( $content, 3000 );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$json_text = trim( $text );
		if ( strpos( $json_text, '```' ) !== false ) {
			$json_text = preg_replace( '/```(json)?/i', '', $json_text );
			$json_text = trim( $json_text );
		}

		$parsed = json_decode( $json_text, true );
		if ( null === $parsed || ! isset( $parsed['items'] ) ) {
			return new WP_Error( 'cpm_bad_json', 'Não consegui interpretar a resposta da IA como JSON. Resposta bruta: ' . $text );
		}

		return $parsed;
	}
}
