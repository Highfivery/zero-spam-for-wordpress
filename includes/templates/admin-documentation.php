<?php
/**
 * Admin Documentation Page Template
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Filter: zerospam_documentation_sections
 *
 * Allows modules to register their own documentation sections.
 *
 * @param array $sections Documentation sections array.
 *
 * Example:
 * add_filter( 'zerospam_documentation_sections', function( $sections ) {
 *     $sections['my-feature'] = array(
 *         'title'    => 'My Feature',
 *         'template' => PLUGIN_PATH . 'includes/templates/docs/my-feature.php',
 *         'priority' => 10,
 *     );
 *     return $sections;
 * } );
 */
$sections = apply_filters(
	'zerospam_documentation_sections',
	array(
		'rest-api'       => array(
			'title'    => __( 'REST API', 'zero-spam' ),
			'template' => ZEROSPAM_PATH . 'includes/templates/docs/rest-api.php',
			'priority' => 10,
		),
		'api-monitoring' => array(
			'title'    => __( 'API Monitoring & Alerts', 'zero-spam' ),
			'template' => ZEROSPAM_PATH . 'includes/templates/docs/api-monitoring.php',
			'priority' => 20,
		),
	)
);

// Sort sections by priority.
uasort(
	$sections,
	function ( $a, $b ) {
		$priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
		$priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
		return $priority_a - $priority_b;
	}
);
?>

<div class="zerospam-documentation">
	<h1><?php esc_html_e( 'Zero Spam Documentation', 'zero-spam' ); ?></h1>
	
	<p class="zerospam-documentation-intro">
		<?php esc_html_e( 'Learn how to use Zero Spam\'s advanced features with our comprehensive guides. Everything is explained in simple terms — no technical expertise required.', 'zero-spam' ); ?>
	</p>

	<?php if ( empty( $sections ) ) : ?>
		<div class="zerospam-block">
			<div class="zerospam-block__content">
				<p><?php esc_html_e( 'No documentation is currently available.', 'zero-spam' ); ?></p>
			</div>
		</div>
	<?php else : ?>
		<!-- Table of Contents -->
		<div class="zerospam-docs-toc">
			<h2><?php esc_html_e( 'Quick Navigation', 'zero-spam' ); ?></h2>
			<ul class="zerospam-docs-toc-list">
				<?php foreach ( $sections as $section_id => $section ) : ?>
					<li>
						<a href="#section-<?php echo esc_attr( $section_id ); ?>">
							<?php echo esc_html( $section['title'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="zerospam-documentation-sections">
			<?php foreach ( $sections as $section_id => $section ) : ?>
				<?php if ( ! empty( $section['template'] ) && file_exists( $section['template'] ) ) : ?>
					<div class="zerospam-documentation-section" id="section-<?php echo esc_attr( $section_id ); ?>">
						<?php
						// Load the section template.
						require $section['template'];
						?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
