<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$columns = array(); // competitor_id => name, union across all items
foreach ( $data as $item ) {
	foreach ( $item['rows'] as $cid => $row ) {
		$columns[ $cid ] = $row['competitor'];
	}
}
asort( $columns );
?>
<div class="wrap cpm-wrap">
	<h1>Painel de preços</h1>

	<?php if ( empty( $data ) ) : ?>
		<div class="cpm-card cpm-wide">
			<p>Ainda não há orçamentos recebidos e confirmados. Assim que você confirmar a leitura de um PDF na tela
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cpm-rounds' ) ); ?>">Rodadas</a>, a comparação aparece aqui.</p>
		</div>
	<?php else : ?>
		<p class="cpm-hint">Célula verde = fornecedor mais barato para aquele item na cotação mais recente. Seta indica se o preço subiu ou desceu em relação à cotação anterior do mesmo fornecedor.</p>
		<div style="overflow-x:auto">
		<table class="cpm-table">
			<thead>
				<tr>
					<th>Item</th>
					<?php foreach ( $columns as $cname ) : ?>
						<th><?php echo esc_html( $cname ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $data as $item ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
					<?php foreach ( $columns as $cid => $cname ) :
						$row = $item['rows'][ $cid ] ?? null;
					?>
						<td class="<?php echo ( $row && $row['is_cheapest'] ) ? 'cpm-cheapest' : ''; ?>">
							<?php if ( $row ) : ?>
								R$ <?php echo esc_html( number_format( $row['latest'], 2, ',', '.' ) ); ?>
								<?php if ( 'up' === $row['trend'] ) : ?>
									<span class="cpm-trend-up">▲</span>
								<?php elseif ( 'down' === $row['trend'] ) : ?>
									<span class="cpm-trend-down">▼</span>
								<?php endif; ?>
								<div class="cpm-hint"><?php echo esc_html( $row['date'] ? date_i18n( 'd/m/Y', strtotime( $row['date'] ) ) : '' ); ?></div>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>
</div>
