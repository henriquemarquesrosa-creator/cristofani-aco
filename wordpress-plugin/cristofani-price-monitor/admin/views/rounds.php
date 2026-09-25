<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cpm-wrap">
	<h1>Rodadas de cotação</h1>

	<?php if ( ! empty( $_GET['cpm_saved'] ) ) : ?>
		<div class="cpm-notice">Feito.</div>
	<?php elseif ( ! empty( $_GET['cpm_deleted'] ) ) : ?>
		<div class="cpm-notice">Rodada removida.</div>
	<?php elseif ( ! empty( $_GET['cpm_ran'] ) ) : ?>
		<div class="cpm-notice">Verificação executada — novas rodadas (se houver) foram criadas abaixo.</div>
	<?php elseif ( ! empty( $_GET['cpm_error'] ) ) : ?>
		<div class="cpm-notice cpm-error"><?php echo esc_html( $_GET['cpm_error'] ); ?></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:16px">
		<?php wp_nonce_field( 'cpm_run_now' ); ?>
		<input type="hidden" name="action" value="cpm_run_now">
		<button type="submit" class="button">Verificar agendas agora</button>
		<span class="cpm-hint">Roda a mesma checagem diária na hora — útil se você acabou de cadastrar um concorrente novo.</span>
	</form>

	<?php if ( $review ) : ?>
		<div class="cpm-card cpm-wide">
			<h2>Revisar orçamento lido do PDF</h2>
			<p class="cpm-hint">
				Fornecedor identificado: <strong><?php echo esc_html( $review['parsed']['supplier'] ?? '—' ); ?></strong> ·
				Data do orçamento: <strong><?php echo esc_html( $review['parsed']['quote_date'] ?? '—' ); ?></strong>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cpm_confirm_quote' ); ?>
				<input type="hidden" name="action" value="cpm_confirm_quote">
				<input type="hidden" name="round_id" value="<?php echo (int) $review['round_id']; ?>">
				<table class="cpm-table">
					<thead>
						<tr><th>Incluir</th><th>Item no PDF</th><th>Corresponde ao item cadastrado</th><th>Qtd</th><th>Unid.</th><th>Preço unit.</th><th>Preço total</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $review['parsed']['items'] as $i => $line ) :
						$raw_name = $line['name'] ?? '';
						$match_id = 0;
						foreach ( $review['items'] as $reg ) {
							if ( $raw_name && false !== stripos( $raw_name, $reg->name ) ) {
								$match_id = $reg->id;
								break;
							}
						}
					?>
						<tr>
							<td><input type="checkbox" name="row[<?php echo $i; ?>][include]" value="1" checked></td>
							<td>
								<?php echo esc_html( $raw_name ); ?>
								<input type="hidden" name="row[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $raw_name ); ?>">
							</td>
							<td>
								<select name="row[<?php echo $i; ?>][item_id]">
									<option value="">— não relacionar —</option>
									<?php foreach ( $review['items'] as $reg ) : ?>
										<option value="<?php echo (int) $reg->id; ?>" <?php selected( $match_id, $reg->id ); ?>><?php echo esc_html( $reg->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="text" name="row[<?php echo $i; ?>][quantity]" value="<?php echo esc_attr( $line['quantity'] ?? '' ); ?>" style="width:70px"></td>
							<td><input type="text" name="row[<?php echo $i; ?>][unit]" value="<?php echo esc_attr( $line['unit'] ?? '' ); ?>" style="width:60px"></td>
							<td><input type="text" name="row[<?php echo $i; ?>][unit_price]" value="<?php echo esc_attr( $line['unit_price'] ?? '' ); ?>" style="width:90px"></td>
							<td><input type="text" name="row[<?php echo $i; ?>][total_price]" value="<?php echo esc_attr( $line['total_price'] ?? '' ); ?>" style="width:90px"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="submit" class="button button-primary">Confirmar e salvar no histórico</button>
				</p>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cpm_cancel_review' ); ?>
				<input type="hidden" name="action" value="cpm_cancel_review">
				<input type="hidden" name="round_id" value="<?php echo (int) $review['round_id']; ?>">
				<button type="submit" class="button">Cancelar revisão</button>
			</form>
		</div>
	<?php endif; ?>

	<table class="cpm-table">
		<thead><tr><th>Concorrente</th><th>Persona</th><th>Itens sorteados</th><th>Rascunho</th><th>Status</th><th>Ações</th></tr></thead>
		<tbody>
		<?php if ( empty( $rounds ) ) : ?>
			<tr><td colspan="6">Nenhuma rodada ainda. Cadastre concorrentes, itens e personas — a próxima verificação diária cria as rodadas automaticamente.</td></tr>
		<?php endif; ?>
		<?php foreach ( $rounds as $r ) :
			$items = json_decode( $r->items_json, true );
		?>
			<tr>
				<td><strong><?php echo esc_html( $r->competitor_name ); ?></strong><div class="cpm-hint"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->created_at ) ) ); ?></div></td>
				<td><?php echo esc_html( $r->persona_name ); ?></td>
				<td>
					<?php if ( $items ) : ?>
						<ul style="margin:0;padding-left:16px">
						<?php foreach ( $items as $it ) : ?>
							<li><?php echo esc_html( $it['quantity'] . ' ' . $it['unit'] . ' — ' . $it['name'] ); ?></li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $r->draft_message ) : ?>
						<div class="cpm-draft"><?php echo esc_html( $r->draft_message ); ?></div>
					<?php else : ?>
						<span class="cpm-hint">Sem rascunho (falha na geração — use "Gerar novo rascunho").</span>
					<?php endif; ?>
				</td>
				<td><span class="cpm-badge <?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $r->status ); ?></span></td>
				<td class="cpm-actions">
					<?php if ( 'pending' === $r->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'cpm_mark_sent' ); ?>
							<input type="hidden" name="action" value="cpm_mark_sent">
							<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
							<button type="submit" class="button button-small">Marquei como enviada</button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'cpm_regenerate_draft' ); ?>
							<input type="hidden" name="action" value="cpm_regenerate_draft">
							<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
							<button type="submit" class="button button-small">Gerar novo rascunho</button>
						</form>
					<?php elseif ( 'sent' === $r->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'cpm_upload_quote' ); ?>
							<input type="hidden" name="action" value="cpm_upload_quote">
							<input type="hidden" name="round_id" value="<?php echo (int) $r->id; ?>">
							<input type="file" name="quote_pdf" accept="application/pdf" required>
							<button type="submit" class="button button-small">Enviar orçamento (PDF)</button>
						</form>
					<?php elseif ( 'received' === $r->status ) : ?>
						<span class="cpm-hint">Orçamento recebido em <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r->received_at ) ) ); ?>.</span>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remover esta rodada?');">
						<?php wp_nonce_field( 'cpm_delete_round' ); ?>
						<input type="hidden" name="action" value="cpm_delete_round">
						<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
						<button type="submit" class="button button-small button-link-delete">Remover</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
