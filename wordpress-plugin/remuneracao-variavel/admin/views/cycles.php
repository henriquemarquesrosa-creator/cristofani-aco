<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rv-wrap">
	<h1>Ciclos (Bimestres)</h1>

	<?php if ( ! empty( $_GET['rv_saved'] ) ) : ?>
		<div class="rv-notice">Feito.</div>
	<?php elseif ( ! empty( $_GET['rv_deleted'] ) ) : ?>
		<div class="rv-notice">Ciclo removido.</div>
	<?php elseif ( ! empty( $_GET['rv_error'] ) ) : ?>
		<div class="rv-notice rv-error"><?php echo esc_html( $_GET['rv_error'] ); ?></div>
	<?php endif; ?>

	<?php if ( ! $detail ) : ?>

		<div class="rv-card">
			<h2>Novo ciclo</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rv_create_cycle' ); ?>
				<input type="hidden" name="action" value="rv_create_cycle">
				<table class="form-table">
					<tr><th><label>Primeiro mês do bimestre</label></th><td><input type="date" name="month1_start" required></td></tr>
					<tr><th><label>Rótulo (opcional)</label></th><td><input type="text" name="label" class="regular-text" placeholder="Ex.: 3º Bimestre 2026"></td></tr>
				</table>
				<p><button type="submit" class="button button-primary">Criar ciclo</button></p>
			</form>
		</div>

		<table class="rv-table">
			<thead><tr><th>Ciclo</th><th>Início</th><th>Status</th><th class="num">Total calculado</th><th>Ações</th></tr></thead>
			<tbody>
			<?php if ( empty( $cycles ) ) : ?>
				<tr><td colspan="5">Nenhum ciclo criado ainda.</td></tr>
			<?php endif; ?>
			<?php foreach ( $cycles as $c ) :
				$total = Rv_Calculator::compute_cycle( $c )['total'];
			?>
				<tr>
					<td><strong><?php echo esc_html( $c->label ); ?></strong></td>
					<td><?php echo esc_html( date_i18n( 'm/Y', strtotime( $c->month1_start ) ) ); ?></td>
					<td><span class="rv-badge <?php echo esc_attr( $c->status ); ?>"><?php echo esc_html( $c->status ); ?></span></td>
					<td class="num">R$ <?php echo number_format( $total, 2, ',', '.' ); ?></td>
					<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rv-cycles', 'id' => $c->id ), admin_url( 'admin.php' ) ) ); ?>">Abrir</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

	<?php else :
		$cycle   = $detail['cycle'];
		$compute = $detail['compute'];
		$months  = $compute['months'];
		$groups  = array();
		foreach ( $compute['rows'] as $r ) {
			$groups[ $r['categoria'] ][] = $r;
		}
	?>

		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rv-cycles' ), admin_url( 'admin.php' ) ) ); ?>">&larr; Todos os ciclos</a></p>

		<div class="rv-card rv-wide">
			<h2><?php echo esc_html( $cycle->label ); ?> <span class="rv-badge <?php echo esc_attr( $cycle->status ); ?>"><?php echo esc_html( $cycle->status ); ?></span></h2>
			<p class="rv-hint">
				Mês 1: <?php echo esc_html( date_i18n( 'M/Y', strtotime( $months['month1']['start'] ) ) ); ?> ·
				Mês 2: <?php echo esc_html( date_i18n( 'M/Y', strtotime( $months['month2']['start'] ) ) ); ?>
			</p>

			<div class="rv-card" style="margin-left:0;margin-right:0;background:#fafafa">
				<h3 style="margin-top:0">Base do bimestre anterior (usada no Crescimento)</h3>
				<p class="rv-hint">
					Calculado a partir dos lançamentos já importados para esse período:
					faturamento R$ <?php echo number_format( $compute['faturamento_anterior_calc'], 2, ',', '.' ); ?>,
					<?php echo (int) $compute['ativos_anterior_calc']; ?> cliente(s) ativo(s).
					Se o relatório daquele bimestre ainda não foi importado, digite os valores reais abaixo pra sobrescrever —
					fica valendo até você apagar o campo.
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'rv_save_baseline' ); ?>
					<input type="hidden" name="action" value="rv_save_baseline">
					<input type="hidden" name="cycle_id" value="<?php echo (int) $cycle->id; ?>">
					<label>Faturamento do bimestre anterior: R$
						<input type="text" class="rv-num" name="baseline_faturamento" value="<?php echo esc_attr( $cycle->baseline_faturamento ); ?>" placeholder="auto">
					</label>
					&nbsp;&nbsp;
					<label>Clientes ativos no bimestre anterior:
						<input type="text" class="rv-num" name="baseline_ativos" value="<?php echo esc_attr( $cycle->baseline_ativos ); ?>" placeholder="auto">
					</label>
					<button type="submit" class="button">Salvar base</button>
				</form>
				<p class="rv-hint">Usado agora: faturamento R$ <?php echo number_format( $compute['faturamento_anterior'], 2, ',', '.' ); ?>, <?php echo (int) $compute['ativos_anterior']; ?> cliente(s) ativo(s)<?php echo $compute['baseline_overridden'] ? ' (manual)' : ' (automático)'; ?>.</p>
			</div>

			<div class="rv-stat"><span class="n">R$ <?php echo number_format( $compute['total'], 2, ',', '.' ); ?></span><span class="l">Total do ciclo</span></div>
			<div class="rv-stat"><span class="n">R$ <?php echo number_format( $compute['faturamento_atual'], 2, ',', '.' ); ?></span><span class="l">Faturamento do bimestre</span></div>
			<div class="rv-stat"><span class="n"><?php echo (int) $compute['ativos_atual']; ?></span><span class="l">Clientes ativos agora</span></div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rv_save_targets' ); ?>
				<input type="hidden" name="action" value="rv_save_targets">
				<input type="hidden" name="cycle_id" value="<?php echo (int) $cycle->id; ?>">

				<?php foreach ( $groups as $categoria => $items ) : ?>
					<h3><?php echo esc_html( $categoria ); ?></h3>
					<table class="rv-table">
						<thead>
							<tr>
								<th>Métrica</th><th>Período</th><th class="num">Meta</th><th class="num">Prêmio-base</th>
								<th class="num">Realizado</th><th class="num">% Atingida</th><th class="num">Valor calculado</th><th class="num">Valor final (ajuste)</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $items as $it ) :
							$row = $it['row'];
							$periodo = 1 === (int) $it['month_index'] ? date_i18n( 'M/Y', strtotime( $months['month1']['start'] ) )
								: ( 2 === (int) $it['month_index'] ? date_i18n( 'M/Y', strtotime( $months['month2']['start'] ) ) : 'Bimestre' );
							$pct_class = $it['pct'] >= 1 ? 'rv-pct-ok' : ( $it['pct'] >= 0.9 ? 'rv-pct-tier' : 'rv-pct-zero' );
						?>
							<tr>
								<td>
									<?php if ( ! $it['auto'] ) : ?>
										<input type="text" name="target[<?php echo (int) $row->id; ?>][title]" value="<?php echo esc_attr( $row->title ); ?>" placeholder="<?php echo esc_attr( $it['label'] ); ?>" class="regular-text">
									<?php else : ?>
										<?php echo esc_html( $it['label'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $periodo ); ?></td>
								<td class="num"><input type="text" class="rv-num" name="target[<?php echo (int) $row->id; ?>][target_value]" value="<?php echo esc_attr( $row->target_value ); ?>"></td>
								<td class="num"><input type="text" class="rv-num" name="target[<?php echo (int) $row->id; ?>][premio_base]" value="<?php echo esc_attr( $row->premio_base ); ?>"></td>
								<td class="num">
									<?php if ( $it['auto'] ) : ?>
										<?php echo number_format( $it['realizado'], 2, ',', '.' ); ?>
									<?php else : ?>
										<input type="text" class="rv-num" name="target[<?php echo (int) $row->id; ?>][realizado_manual]" value="<?php echo esc_attr( $row->realizado_manual ); ?>">
									<?php endif; ?>
								</td>
								<td class="num"><span class="<?php echo esc_attr( $pct_class ); ?>"><?php echo number_format( $it['pct'] * 100, 1, ',', '.' ); ?>%</span></td>
								<td class="num">R$ <?php echo number_format( $it['valor_calculado'], 2, ',', '.' ); ?></td>
								<td class="num">
									<input type="text" class="rv-num rv-override" name="target[<?php echo (int) $row->id; ?>][valor_override]" value="<?php echo esc_attr( $row->valor_override ); ?>" placeholder="auto">
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<p><button type="submit" class="button button-primary">Salvar metas / ajustes</button></p>
			</form>

			<p>
				<?php if ( 'aberto' === $cycle->status ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Fechar este ciclo? Os valores ficam travados como histórico.');">
						<?php wp_nonce_field( 'rv_close_cycle' ); ?>
						<input type="hidden" name="action" value="rv_close_cycle">
						<input type="hidden" name="id" value="<?php echo (int) $cycle->id; ?>">
						<button type="submit" class="button">Fechar ciclo</button>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'rv_reopen_cycle' ); ?>
						<input type="hidden" name="action" value="rv_reopen_cycle">
						<input type="hidden" name="id" value="<?php echo (int) $cycle->id; ?>">
						<button type="submit" class="button">Reabrir ciclo</button>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Excluir este ciclo e todas as metas dele? Não dá pra desfazer.');">
					<?php wp_nonce_field( 'rv_delete_cycle' ); ?>
					<input type="hidden" name="action" value="rv_delete_cycle">
					<input type="hidden" name="id" value="<?php echo (int) $cycle->id; ?>">
					<button type="submit" class="button button-link-delete">Excluir ciclo</button>
				</form>
			</p>
		</div>

	<?php endif; ?>
</div>
