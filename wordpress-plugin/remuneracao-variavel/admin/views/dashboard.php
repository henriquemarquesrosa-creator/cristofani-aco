<?php if ( ! defined( 'ABSPATH' ) ) exit;
$settings = get_option( 'rv_settings' );
?>
<div class="wrap rv-wrap">
	<h1>Remuneração Variável<?php echo $settings['vendedor_nome'] ? ' — ' . esc_html( $settings['vendedor_nome'] ) : ''; ?></h1>

	<?php if ( ! $cycle ) : ?>
		<div class="rv-card">
			<p>Nenhum ciclo em aberto no momento.</p>
			<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rv-cycles' ), admin_url( 'admin.php' ) ) ); ?>">Criar novo ciclo</a></p>
		</div>
	<?php else : ?>
		<div class="rv-card rv-wide">
			<h2>Ciclo em aberto: <?php echo esc_html( $cycle->label ); ?></h2>
			<div class="rv-stat"><span class="n">R$ <?php echo number_format( $compute['total'], 2, ',', '.' ); ?></span><span class="l">Total calculado até agora</span></div>
			<div class="rv-stat"><span class="n">R$ <?php echo number_format( $compute['faturamento_atual'], 2, ',', '.' ); ?></span><span class="l">Faturamento do bimestre</span></div>
			<div class="rv-stat"><span class="n"><?php echo (int) $compute['ativos_atual']; ?></span><span class="l">Clientes ativos</span></div>
			<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rv-cycles', 'id' => $cycle->id ), admin_url( 'admin.php' ) ) ); ?>">Ver detalhes e ajustar metas</a></p>
		</div>
	<?php endif; ?>

	<div class="rv-card rv-wide">
		<h2>Histórico de ciclos fechados</h2>
		<table class="rv-table">
			<thead><tr><th>Ciclo</th><th>Início</th><th class="num">Total pago</th><th>Ações</th></tr></thead>
			<tbody>
			<?php if ( empty( $closed ) ) : ?>
				<tr><td colspan="4">Nenhum ciclo fechado ainda.</td></tr>
			<?php endif; ?>
			<?php foreach ( $closed as $c ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $c->label ); ?></strong></td>
					<td><?php echo esc_html( date_i18n( 'm/Y', strtotime( $c->month1_start ) ) ); ?></td>
					<td class="num">R$ <?php echo number_format( $closed_totals[ $c->id ], 2, ',', '.' ); ?></td>
					<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rv-cycles', 'id' => $c->id ), admin_url( 'admin.php' ) ) ); ?>">Ver</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
