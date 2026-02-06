<?php
/**
 * Provides the view for the Logs tab in the admin dashboard.
 *
 * @link       https://clickrank.ai/
 * @since      3.0.0
 *
 * @package    ClickRank_AI
 * @subpackage ClickRank_AI/includes/admin/views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

global $wpdb;
$table_name = $wpdb->prefix . 'clickrank_ai_logs';

$per_page     = 25;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;
$total_logs   = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
$total_pages  = $total_logs ? ceil( $total_logs / $per_page ) : 1;

$logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	)
);
?>
<div class="bg-white shadow-sm rounded-lg">
	<div class="p-6 border-b border-gray-200 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
		<div>
			<h2 class="text-xl font-bold text-gray-800"><?php esc_html_e( 'Webhook Logs', 'clickrank-ai' ); ?></h2>
			<p class="text-gray-600 mt-2"><?php esc_html_e( 'A record of the most recent events received from ClickRank.ai.', 'clickrank-ai' ); ?></p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="clickrank_ai_clear_logs">
			<?php wp_nonce_field( 'clickrank_ai_clear_logs_nonce' ); ?>
			<button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-red-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
				<?php esc_html_e( 'Clear All Logs', 'clickrank-ai' ); ?>
			</button>
		</form>
	</div>
	<div class="overflow-x-auto">
		<table class="min-w-full divide-y divide-gray-200">
			<thead class="bg-gray-50">
				<tr>
					<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4"><?php esc_html_e( 'Timestamp', 'clickrank-ai' ); ?></th>
					<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6"><?php esc_html_e( 'Level', 'clickrank-ai' ); ?></th>
					<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Message', 'clickrank-ai' ); ?></th>
				</tr>
			</thead>
			<tbody class="bg-white divide-y divide-gray-200">
				<?php if ( ! empty( $logs ) ) : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?php echo esc_html( $log->time ); ?></td>
							<td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
								<?php
								$level_color = 'text-gray-600';
								if ( 'ERROR' === $log->level ) {
									$level_color = 'text-red-600';
								} elseif ( 'WARNING' === $log->level ) {
									$level_color = 'text-yellow-600';
								} elseif ( 'INFO' === $log->level ) {
									$level_color = 'text-blue-600';
								}
								?>
								<span class="<?php echo esc_attr( $level_color ); ?>"><?php echo esc_html( $log->level ); ?></span>
							</td>
							<td class="px-6 py-4 whitespace-pre-wrap text-sm text-gray-600 break-words font-mono"><?php echo esc_html( $log->message ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500"><?php esc_html_e( 'No logs found.', 'clickrank-ai' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ( $total_pages > 1 ) : ?>
		<div class="p-6 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
			<p class="text-sm text-gray-700">
				<?php
				printf(
					/* translators: 1: Current page number, 2: Total pages number */
					esc_html__( 'Page %1$s of %2$s', 'clickrank-ai' ),
					'<span class="font-medium">' . absint( $current_page ) . '</span>',
					'<span class="font-medium">' . absint( $total_pages ) . '</span>'
				);
				?>
			</p>
			<div class="pagination-links text-sm">
				<?php
				$pagination_html = paginate_links(
					[
						'base'      => admin_url( 'admin.php?page=' . CLICKRANK_AI_MENU_SLUG . '&tab=logs&paged=%#%' ),
						'format'    => '?paged=%#%',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => '&laquo; ' . __( 'Previous', 'clickrank-ai' ),
						'next_text' => __( 'Next', 'clickrank-ai' ) . ' &raquo;',
					]
				);
				echo wp_kses_post( $pagination_html );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>