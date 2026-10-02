<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rv-wrap">
	<h1>Configurações</h1>

	<?php if ( ! empty( $_GET['rv_saved'] ) ) : ?>
		<div class="rv-notice">Configurações salvas.</div>
	<?php endif; ?>

	<div class="rv-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rv_save_settings' ); ?>
			<input type="hidden" name="action" value="rv_save_settings">
			<table class="form-table">
				<tr><th><label>Nome do vendedor</label></th><td><input type="text" name="vendedor_nome" value="<?php echo esc_attr( $settings['vendedor_nome'] ); ?>" class="regular-text"></td></tr>
				<tr><th colspan="2"><h3 style="margin-bottom:0">Limites (regra de elegibilidade)</h3></th></tr>
				<tr><th><label>Limite "cliente novo" (abertura)</label></th><td>R$ <input type="text" name="limite_abertura" value="<?php echo esc_attr( $settings['limite_abertura'] ); ?>" class="rv-num"><p class="rv-hint">Cliente precisa faturar esse valor (acumulado desde a 1ª compra) para contar como "abertura de novo cliente".</p></td></tr>
				<tr><th><label>Limite "cliente ativo"</label></th><td>R$ <input type="text" name="limite_ativo" value="<?php echo esc_attr( $settings['limite_ativo'] ); ?>" class="rv-num"><p class="rv-hint">Faturamento mínimo no bimestre para o cliente contar como "ativo".</p></td></tr>
				<tr><th colspan="2"><h3 style="margin-bottom:0">Prêmio-base padrão (sugerido ao criar um novo ciclo)</h3></th></tr>
				<tr><th><label>Abertura de novos clientes</label></th><td>R$ <input type="text" name="premio_abertura" value="<?php echo esc_attr( $settings['premio_abertura'] ); ?>" class="rv-num"></td></tr>
				<tr><th><label>Faturamento na Aquisição</label></th><td>R$ <input type="text" name="premio_aquisicao" value="<?php echo esc_attr( $settings['premio_aquisicao'] ); ?>" class="rv-num"></td></tr>
				<tr><th><label>Crescimento da Base de Clientes Ativos</label></th><td>R$ <input type="text" name="premio_base_ativos" value="<?php echo esc_attr( $settings['premio_base_ativos'] ); ?>" class="rv-num"></td></tr>
				<tr><th><label>Crescimento do Faturamento no Bimestre</label></th><td>R$ <input type="text" name="premio_fat_bimestre" value="<?php echo esc_attr( $settings['premio_fat_bimestre'] ); ?>" class="rv-num"></td></tr>
				<tr><th><label>Faturamento da Carteira</label></th><td>R$ <input type="text" name="premio_carteira" value="<?php echo esc_attr( $settings['premio_carteira'] ); ?>" class="rv-num"></td></tr>
			</table>
			<p><button type="submit" class="button button-primary">Salvar</button></p>
		</form>
	</div>

	<div class="rv-card rv-wide">
		<h2>Como funciona o cálculo</h2>
		<ul>
			<li><strong>Negócio Certo</strong> (mensal, 2x por ciclo): Abertura de novos clientes e Faturamento na Aquisição são calculados automaticamente a partir do relatório importado.</li>
			<li><strong>Construção do Negócio / Resultado</strong> (bimestral): Crescimento da Base, Crescimento do Faturamento e Faturamento da Carteira também são automáticos.</li>
			<li><strong>Desafios</strong> (mês e bimestre): são livres — você define o título, a meta e digita o realizado manualmente a cada ciclo.</li>
			<li><strong>Regra de pagamento:</strong> atingiu 100% ou mais da meta → paga proporcional (Realizado/Meta × Prêmio-base), sem teto. Nas métricas Faturamento na Aquisição, Crescimento do Faturamento no Bimestre e Faturamento da Carteira, entre 90% e 99,9% da meta paga 50% fixo do prêmio-base. Abaixo de 90% (ou abaixo de 100% nas demais métricas): zero. Qualquer linha pode ser ajustada manualmente na tela do ciclo, se precisar abrir uma exceção.</li>
		</ul>
	</div>
</div>
