<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cpm-wrap">
	<h1>Concorrentes</h1>

	<?php if ( ! empty( $_GET['cpm_saved'] ) ) : ?>
		<div class="cpm-notice">Concorrente salvo.</div>
	<?php elseif ( ! empty( $_GET['cpm_deleted'] ) ) : ?>
		<div class="cpm-notice">Concorrente removido.</div>
	<?php elseif ( ! empty( $_GET['cpm_error'] ) ) : ?>
		<div class="cpm-notice cpm-error"><?php echo esc_html( $_GET['cpm_error'] ); ?></div>
	<?php endif; ?>

	<div class="cpm-card">
		<h2>Adicionar concorrente</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cpm_save_competitor' ); ?>
			<input type="hidden" name="action" value="cpm_save_competitor">
			<table class="form-table">
				<tr><th><label>Nome</label></th><td><input type="text" name="name" class="regular-text" required></td></tr>
				<tr><th><label>Telefone (WhatsApp)</label></th><td><input type="text" name="phone" class="regular-text" placeholder="(16) 90000-0000"></td></tr>
				<tr><th><label>Intervalo entre rodadas (dias)</label></th><td><input type="number" name="interval_days" value="30" min="1" style="width:90px"></td></tr>
				<tr><th><label>Ativo</label></th><td><label><input type="checkbox" name="active" value="1" checked> gerar rodadas para este concorrente</label></td></tr>
				<tr><th><label>Observações</label></th><td><textarea name="notes" class="large-text" rows="2"></textarea></td></tr>
			</table>
			<p><button type="submit" class="button button-primary">Salvar</button></p>
		</form>
	</div>

	<table class="cpm-table">
		<thead><tr><th>Nome</th><th>Telefone</th><th>Intervalo</th><th>Última rodada</th><th>Status</th><th>Ações</th></tr></thead>
		<tbody>
		<?php if ( empty( $competitors ) ) : ?>
			<tr><td colspan="6">Nenhum concorrente cadastrado ainda.</td></tr>
		<?php endif; ?>
		<?php foreach ( $competitors as $c ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $c->name ); ?></strong><?php echo $c->notes ? '<div class="cpm-hint">' . esc_html( $c->notes ) . '</div>' : ''; ?></td>
				<td><?php echo esc_html( $c->phone ); ?></td>
				<td><?php echo esc_html( $c->interval_days ); ?> dias</td>
				<td><?php echo $c->last_round_at ? esc_html( date_i18n( 'd/m/Y', strtotime( $c->last_round_at ) ) ) : '—'; ?></td>
				<td><?php echo $c->active ? '<span class="cpm-badge received">ativo</span>' : '<span class="cpm-badge pending">pausado</span>'; ?></td>
				<td class="cpm-actions">
					<details>
						<summary class="button button-small">Editar</summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
							<?php wp_nonce_field( 'cpm_save_competitor' ); ?>
							<input type="hidden" name="action" value="cpm_save_competitor">
							<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
							<p><input type="text" name="name" value="<?php echo esc_attr( $c->name ); ?>" class="regular-text" required></p>
							<p><input type="text" name="phone" value="<?php echo esc_attr( $c->phone ); ?>" class="regular-text"></p>
							<p>Intervalo: <input type="number" name="interval_days" value="<?php echo (int) $c->interval_days; ?>" min="1" style="width:80px"> dias</p>
							<p><label><input type="checkbox" name="active" value="1" <?php checked( $c->active, 1 ); ?>> ativo</label></p>
							<p><textarea name="notes" class="large-text" rows="2"><?php echo esc_textarea( $c->notes ); ?></textarea></p>
							<p><button type="submit" class="button button-primary button-small">Atualizar</button></p>
						</form>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remover este concorrente e seu histórico ligado a ele?');">
						<?php wp_nonce_field( 'cpm_delete_competitor' ); ?>
						<input type="hidden" name="action" value="cpm_delete_competitor">
						<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
						<button type="submit" class="button button-small button-link-delete">Remover</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
