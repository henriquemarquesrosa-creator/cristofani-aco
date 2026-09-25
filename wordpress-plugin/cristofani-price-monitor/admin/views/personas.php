<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cpm-wrap">
	<h1>Personas</h1>
	<p class="cpm-hint">Cada persona é um jeito de escrever diferente, usado para gerar o rascunho da mensagem de cotação. Quanto mais personas cadastradas, menos repetitivo fica entre rodadas.</p>

	<?php if ( ! empty( $_GET['cpm_saved'] ) ) : ?>
		<div class="cpm-notice">Persona salva.</div>
	<?php elseif ( ! empty( $_GET['cpm_deleted'] ) ) : ?>
		<div class="cpm-notice">Persona removida.</div>
	<?php elseif ( ! empty( $_GET['cpm_error'] ) ) : ?>
		<div class="cpm-notice cpm-error"><?php echo esc_html( $_GET['cpm_error'] ); ?></div>
	<?php endif; ?>

	<div class="cpm-card">
		<h2>Adicionar persona</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'cpm_save_persona' ); ?>
			<input type="hidden" name="action" value="cpm_save_persona">
			<table class="form-table">
				<tr><th><label>Nome da persona</label></th><td><input type="text" name="name" class="regular-text" placeholder="Ex.: João, obra residencial" required></td></tr>
				<tr><th><label>Jeito de falar / contexto</label></th><td><textarea name="voice" class="large-text" rows="3" placeholder="Ex.: cliente direto, meio informal, está reformando o telhado de casa, escreve frases curtas" required></textarea></td></tr>
				<tr><th><label>Ativa</label></th><td><label><input type="checkbox" name="active" value="1" checked> entra no sorteio das rodadas</label></td></tr>
			</table>
			<p><button type="submit" class="button button-primary">Salvar</button></p>
		</form>
	</div>

	<table class="cpm-table">
		<thead><tr><th>Nome</th><th>Jeito de falar</th><th>Status</th><th>Ações</th></tr></thead>
		<tbody>
		<?php if ( empty( $personas ) ) : ?>
			<tr><td colspan="4">Nenhuma persona cadastrada ainda.</td></tr>
		<?php endif; ?>
		<?php foreach ( $personas as $p ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $p->name ); ?></strong></td>
				<td><?php echo esc_html( $p->voice ); ?></td>
				<td><?php echo $p->active ? '<span class="cpm-badge received">ativa</span>' : '<span class="cpm-badge pending">pausada</span>'; ?></td>
				<td class="cpm-actions">
					<details>
						<summary class="button button-small">Editar</summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
							<?php wp_nonce_field( 'cpm_save_persona' ); ?>
							<input type="hidden" name="action" value="cpm_save_persona">
							<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
							<p><input type="text" name="name" value="<?php echo esc_attr( $p->name ); ?>" class="regular-text" required></p>
							<p><textarea name="voice" class="large-text" rows="3" required><?php echo esc_textarea( $p->voice ); ?></textarea></p>
							<p><label><input type="checkbox" name="active" value="1" <?php checked( $p->active, 1 ); ?>> ativa</label></p>
							<p><button type="submit" class="button button-primary button-small">Atualizar</button></p>
						</form>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remover esta persona?');">
						<?php wp_nonce_field( 'cpm_delete_persona' ); ?>
						<input type="hidden" name="action" value="cpm_delete_persona">
						<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
						<button type="submit" class="button button-small button-link-delete">Remover</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
