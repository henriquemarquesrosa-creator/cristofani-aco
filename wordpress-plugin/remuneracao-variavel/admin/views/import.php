<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rv-wrap">
	<h1>Importar Relatório de Vendas</h1>

	<?php if ( isset( $_GET['rv_inserted'] ) ) : ?>
		<div class="rv-notice">
			<?php echo (int) $_GET['rv_inserted']; ?> linha(s) importada(s).
			<?php if ( ! empty( $_GET['rv_duplicated'] ) ) : ?> <?php echo (int) $_GET['rv_duplicated']; ?> já existiam e foram ignoradas.<?php endif; ?>
			<?php if ( ! empty( $_GET['rv_skipped'] ) ) : ?> <?php echo (int) $_GET['rv_skipped']; ?> linha(s) não puderam ser lidas (data, cliente ou valor em branco/inválido).<?php endif; ?>
		</div>
	<?php elseif ( ! empty( $_GET['rv_error'] ) ) : ?>
		<div class="rv-notice rv-error"><?php echo esc_html( $_GET['rv_error'] ); ?></div>
	<?php endif; ?>

	<div class="rv-card rv-wide">
		<p>Abra o relatório de vendas no Excel, selecione o intervalo <strong>incluindo a linha de cabeçalho</strong> (as colunas precisam ter <code>DATA</code>, <code>DOCUMENTO</code>, <code>CLIENTE</code> e <code>VR. LIQ.</code> — as outras colunas são ignoradas), copie (Ctrl+C) e cole aqui embaixo.</p>
		<p class="rv-hint">Pode colar o relatório inteiro, mesmo com meses que você já importou antes — linhas repetidas (mesmo número de documento) são identificadas e ignoradas automaticamente, não duplicam.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rv_import' ); ?>
			<input type="hidden" name="action" value="rv_import_commit">
			<textarea name="raw_report" class="rv-paste" rows="16" placeholder="Cole aqui o relatório copiado do Excel..." required></textarea>
			<p><button type="submit" class="button button-primary">Importar</button></p>
		</form>
	</div>

	<div class="rv-card">
		<p class="rv-hint"><strong><?php echo number_format( $total_rows, 0, ',', '.' ); ?></strong> lançamentos no histórico até agora.</p>
	</div>
</div>
