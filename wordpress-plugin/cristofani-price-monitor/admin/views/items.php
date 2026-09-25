<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cpm-wrap">
	<h1>Itens a cotar</h1>

	<?php if ( ! empty( $_GET['cpm_saved'] ) ) : ?>
		<div class="cpm-notice">Item salvo.</div>
	<?php elseif ( ! empty( $_GET['cpm_deleted'] ) ) : ?>
		<div class="cpm-notice">Item removido.</div>
	<?php elseif ( ! empty( $_GET['cpm_error'] ) ) : ?>
		<div class="cpm-notice cpm-error"><?php echo esc_html( $_GET['cpm_error'] ); ?></div>
	<?php endif; ?>

	<div class="cpm-card">
		<h2>Adicionar item</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cpm_save_item' ); ?>
			<input type="hidden" name="action" value="cpm_save_item">
			<table class="form-table">
				<tr><th><label>Nome</label></th><td><input type="text" name="name" class="regular-text" placeholder="Ex.: Telha cerâmica" required></td></tr>
				<tr><th><label>Especificação</label></th><td><input type="text" name="spec" class="regular-text" placeholder="Ex.: modelo plan, cor natural"></td></tr>
				<tr><th><label>Unidade</label></th><td><input type="text" name="unit" value="un" style="width:100px"></td></tr>
				<tr><th><label>Ativo</label></th><td><label><input type="checkbox" name="active" value="1" checked> entra no sorteio das rodadas</label></td></tr>
			</table>
			<p><button type="submit" class="button button-primary">Salvar</button></p>
		</form>
	</div>

	<table class="cpm-table">
		<thead><tr><th>Nome</th><th>Especificação</th><th>Unidade</th><th>Status</th><th>Ações</th></tr></thead>
		<tbody>
		<?php if ( empty( $items ) ) : ?>
			<tr><td colspan="5">Nenhum item cadastrado ainda.</td></tr>
		<?php endif; ?>
		<?php foreach ( $items as $it ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $it->name ); ?></strong></td>
				<td><?php echo esc_html( $it->spec ); ?></td>
				<td><?php echo esc_html( $it->unit ); ?></td>
				<td><?php echo $it->active ? '<span class="cpm-badge received">ativo</span>' : '<span class="cpm-badge pending">pausado</span>'; ?></td>
				<td class="cpm-actions">
					<details>
						<summary class="button button-small">Editar</summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
							<?php wp_nonce_field( 'cpm_save_item' ); ?>
							<input type="hidden" name="action" value="cpm_save_item">
							<input type="hidden" name="id" value="<?php echo (int) $it->id; ?>">
							<p><input type="text" name="name" value="<?php echo esc_attr( $it->name ); ?>" class="regular-text" required></p>
							<p><input type="text" name="spec" value="<?php echo esc_attr( $it->spec ); ?>" class="regular-text"></p>
							<p><input type="text" name="unit" value="<?php echo esc_attr( $it->unit ); ?>" style="width:100px"></p>
							<p><label><input type="checkbox" name="active" value="1" <?php checked( $it->active, 1 ); ?>> ativo</label></p>
							<p><button type="submit" class="button button-primary button-small">Atualizar</button></p>
						</form>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remover este item?');">
						<?php wp_nonce_field( 'cpm_delete_item' ); ?>
						<input type="hidden" name="action" value="cpm_delete_item">
						<input type="hidden" name="id" value="<?php echo (int) $it->id; ?>">
						<button type="submit" class="button button-small button-link-delete">Remover</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
