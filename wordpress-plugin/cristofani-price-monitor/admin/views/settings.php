<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cpm-wrap">
	<h1>Configurações</h1>

	<?php if ( ! empty( $_GET['cpm_saved'] ) ) : ?>
		<div class="cpm-notice">Configurações salvas.</div>
	<?php endif; ?>

	<div class="cpm-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cpm_save_settings' ); ?>
			<input type="hidden" name="action" value="cpm_save_settings">
			<table class="form-table">
				<tr>
					<th><label>Chave da API da Anthropic</label></th>
					<td>
						<input type="password" name="anthropic_api_key" class="regular-text" placeholder="<?php echo $settings['anthropic_api_key'] ? '•••••••• (deixe em branco para manter)' : 'sk-ant-...'; ?>">
						<p class="cpm-hint">Usada para gerar as mensagens e ler os PDFs de orçamento. Fica salva no banco de dados do site — deixe em branco para manter a atual.</p>
					</td>
				</tr>
				<tr>
					<th><label>Modelo</label></th>
					<td><input type="text" name="anthropic_model" value="<?php echo esc_attr( $settings['anthropic_model'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label>E-mail para avisos de rodada</label></th>
					<td><input type="email" name="notify_email" value="<?php echo esc_attr( $settings['notify_email'] ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary">Salvar</button></p>
		</form>
	</div>

	<div class="cpm-card">
		<h2>Como funciona o agendamento</h2>
		<p class="cpm-hint">
			Uma vez por dia o WordPress verifica todos os concorrentes ativos. Quando o intervalo configurado para um concorrente
			é atingido (contado desde a última rodada), o sistema sorteia uma persona e alguns itens, gera o rascunho da mensagem
			e te avisa por e-mail. Nada é enviado automaticamente — você revisa e manda pelo WhatsApp manualmente na tela Rodadas.
		</p>
	</div>
</div>
