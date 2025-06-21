<?php
/**
 * Commio Engine - Search and Filter Module
 *
 * This module provides search and filtering capabilities for Commio Engine.
 * It allows users to add customizable search forms via a shortcode.
 *
 * @package CommioEngine\Modules\SearchAndFilter
 * @version 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Commio_Engine_Search_Filter_Module' ) ) {

	/**
	 * Class Commio_Engine_Search_Filter_Module.
	 *
	 * Handles the Search and Filter functionality.
	 */
	class Commio_Engine_Search_Filter_Module {

		/**
		 * Stores the WP_Query object for the current search.
		 * @var WP_Query|null
		 */
		private $search_query = null;

		/**
		 * Constructor.
		 *
		 * Hooks into WordPress to initialize the module by setting up
		 * actions for shortcode registration and asset enqueuing.
		 */
		public function __construct() {
			// Add actions to register shortcodes and enqueue assets.
			add_action( 'init', array( $this, 'register_shortcodes' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Register the shortcodes for the module.
		 */
		public function register_shortcodes() {
			// Shortcode for displaying the search and filter form.
			add_shortcode( 'commio_search_filter', array( $this, 'render_search_filter_shortcode' ) );
		}

		/**
		 * Renders the search and filter form via shortcode.
		 *
		 * Attributes (examples, will be expanded):
		 * - 'show_filters': comma-separated list of filters to display (e.g., "categories,tags,authors,post_types,date_range,custom_fields")
		 * - 'post_types': comma-separated list of post types to search (e.g., "post,page")
		 * - 'custom_fields': comma-separated list of custom meta keys to include as filters.
		 *
		 * @param array $atts Shortcode attributes.
		 * @return string HTML output for the search form and results.
		 */
		public function render_search_filter_shortcode( $atts ) {
			// Sanitize and parse attributes with defaults.
			$atts = shortcode_atts(
				array(
					'show_filters'  => 'keywords,post_types,categories,tags', // Default filters to show
					'post_types'    => 'post', // Default post types to search
					'custom_fields' => '',     // No custom fields by default
					// Add more attributes as needed: authors, date_range, specific taxonomies etc.
				),
				$atts,
				'commio_search_filter'
			);

			// Start output buffering.
			ob_start();

			// Handle search submission and prepare query.
			if ( isset( $_GET['commio_search'] ) && $_GET['commio_search'] === '1' ) {
				$this->handle_search_submission_and_get_results( $atts );
			}

			// Render the search form.
			$this->display_search_form( $atts );

			// Display search results, if any.
			if ( $this->search_query !== null ) {
				$this->display_search_results( $atts );
			}

			// Get the buffered content.
			$output = ob_get_clean();
			return $output;
		}

		/**
		 * Displays the actual HTML for the search form.
		 *
		 * @param array $atts Parsed shortcode attributes.
		 */
		public function display_search_form( $atts ) {
			?>
			<div class="commio-search-filter-wrapper">
				<form role="search" method="get" class="commio-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<input type="hidden" name="commio_search" value="1" /> <?php // Hidden field to identify our search ?>
					<?php
					if ( ! empty( $_GET['s'] ) && empty( $_GET['commio_search'] ) ) {
						// If it's a default WordPress search, keep the 's' parameter
						printf( '<input type="hidden" name="s" value="%s" />', esc_attr( sanitize_text_field( $_GET['s'] ) ) );
					}
					?>

					<h3>Search & Filter</h3>

					<div class="commio-search-fields">
						<!-- Keyword Search (always available for now) -->
						<div class="commio-filter-item commio-filter-keywords">
							<label for="commio-keywords"><?php esc_html_e( 'Keywords', 'commio-engine' ); ?></label>
							<input type="search" id="commio-keywords" name="s" value="<?php echo esc_attr( get_search_query( false ) ); ?>" placeholder="<?php esc_attr_e( 'Enter keywords...', 'commio-engine' ); ?>" />
						</div>

						<?php
						$active_filters = ! empty( $atts['show_filters'] ) ? array_map( 'trim', explode( ',', $atts['show_filters'] ) ) : array();
						$target_post_types = ! empty( $atts['post_types'] ) ? array_map( 'trim', explode( ',', $atts['post_types'] ) ) : array( 'post' );


						if ( in_array( 'post_types', $active_filters ) ) {
							$this->render_post_types_filter( $target_post_types );
						}

						if ( in_array( 'categories', $active_filters ) ) {
							$this->render_taxonomy_filter( 'category', __( 'Categories', 'commio-engine' ) );
						}

						if ( in_array( 'tags', $active_filters ) ) {
							$this->render_taxonomy_filter( 'post_tag', __( 'Tags', 'commio-engine' ) );
						}

						// Placeholder for other filters like authors, date_range, custom_fields
						if ( in_array( 'authors', $active_filters ) ) {
							echo '<div class="commio-filter-item commio-filter-authors"><p><em>Author filter placeholder.</em></p></div>';
						}
						if ( in_array( 'date_range', $active_filters ) ) {
							echo '<div class="commio-filter-item commio-filter-date-range"><p><em>Date range filter placeholder.</em></p></div>';
						}
						if ( in_array( 'custom_fields', $active_filters ) && ! empty( $atts['custom_fields'] ) ) {
							echo '<div class="commio-filter-item commio-filter-custom-fields"><p><em>Custom fields filter placeholder. (' . esc_html($atts['custom_fields']) . ')</em></p></div>';
						}

						?>
					</div>

					<div class="commio-search-submit">
						<input type="submit" value="<?php esc_attr_e( 'Search', 'commio-engine' ); ?>" />
					</div>
				</form>
			</div>
			<?php
		}

		/**
		 * Renders the post type filter.
		 *
		 * @param array $target_post_types Post types specified in the shortcode.
		 */
		protected function render_post_types_filter( $target_post_types ) {
			$available_post_types = get_post_types( array( 'public' => true, 'exclude_from_search' => false ), 'objects' );
			$current_post_types = isset( $_GET['filter_post_type'] ) ? (array) $_GET['filter_post_type'] : $target_post_types; // Default to shortcode atts if not in GET

			// Filter available_post_types to only include those specified in $target_post_types if the shortcode has specific ones
			$filtered_available_post_types = array();
			if ( !(count($target_post_types) == 1 && $target_post_types[0] == 'any') && !empty($target_post_types) ) { // 'any' means all public post types from shortcode perspective
				foreach ( $available_post_types as $slug => $pt ) {
					if ( in_array( $slug, $target_post_types ) ) {
						$filtered_available_post_types[$slug] = $pt;
					}
				}
			} else {
				$filtered_available_post_types = $available_post_types;
			}


			if ( empty( $filtered_available_post_types ) ) {
				return;
			}
			?>
			<div class="commio-filter-item commio-filter-post-types">
				<label><?php esc_html_e( 'Post Types', 'commio-engine' ); ?></label>
				<?php if ( count( $filtered_available_post_types ) > 3 ) : // Use dropdown for many post types ?>
					<select name="filter_post_type[]" id="commio-post-types" multiple size="3">
						<?php foreach ( $filtered_available_post_types as $slug => $post_type_obj ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $current_post_types ) ); ?>>
								<?php echo esc_html( $post_type_obj->labels->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : // Use checkboxes for fewer post types ?>
					<ul>
						<?php foreach ( $filtered_available_post_types as $slug => $post_type_obj ) : ?>
							<li>
								<label>
									<input type="checkbox" name="filter_post_type[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $current_post_types ) ); ?>>
									<?php echo esc_html( $post_type_obj->labels->name ); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Renders a taxonomy filter (e.g., categories, tags).
		 *
		 * @param string $taxonomy Taxonomy slug.
		 * @param string $label    Filter label.
		 */
		protected function render_taxonomy_filter( $taxonomy, $label ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			) );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return;
			}

			$current_terms = isset( $_GET[ 'filter_tax_' . $taxonomy ] ) ? (array) $_GET[ 'filter_tax_' . $taxonomy ] : array();
			?>
			<div class="commio-filter-item commio-filter-taxonomy-<?php echo esc_attr( $taxonomy ); ?>">
				<label for="commio-<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label>
				<?php if ( count( $terms ) > 7 ) : // Use dropdown for many terms ?>
					<select name="filter_tax_<?php echo esc_attr( $taxonomy ); ?>[]" id="commio-<?php echo esc_attr( $taxonomy ); ?>" multiple size="5">
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current_terms ) ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : // Use checkboxes for fewer terms ?>
					<ul>
						<?php foreach ( $terms as $term ) : ?>
							<li>
								<label>
									<input type="checkbox" name="filter_tax_<?php echo esc_attr( $taxonomy ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $current_terms ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		}


		/**
		 * Enqueue scripts and styles for the module.
		 */
		public function enqueue_assets() {
			// Only enqueue if the shortcode is likely to be used on the page.
			// A more robust check would be to see if the global $post object contains the shortcode,
			// but that's more complex. For now, enqueue on singulars or if it's a search results page.
			// A better approach for performance would be to register them here and only enqueue them
			// within the shortcode handler if the shortcode is actually rendered.
			// However, some argue styles should be available globally if a shortcode *could* be anywhere.

			// Let's assume the shortcode handler will specifically enqueue when it runs.
			// So, this function can be used to *register* assets.
			// Or, if we decide to enqueue conditionally:
			// if ( is_singular() || is_search() ) { // Example condition
			// }

			// For simplicity in this module, and because the shortcode itself handles output,
			// we'll enqueue directly. If this were a larger plugin, a more conditional loading
			// strategy or registering and then enqueuing in the shortcode would be better.

			// Let's define the plugin URL and version for assets.
			// IMPORTANT: This assumes the main Commio Engine plugin file is in the 'commio-engine' directory,
			// and this module is in 'commio-engine/includes/module/'.
			// If Commio Engine has a defined constant for its base URL or version, that should be used.
			// For now, we'll derive it. `plugin_dir_url( __FILE__ )` gives URL to current module dir.

			$module_dir_url = plugin_dir_url( __FILE__ ); // URL to '.../includes/module/'
			$plugin_base_url = $module_dir_url . '../../'; // URL to '.../commio-engine/'
			$version = '1.0.0'; // Ideally get this from a central plugin constant.

			wp_enqueue_style(
				'commio-search-filter-style',
				$plugin_base_url . 'assets/css/search-filter.css',
				array(),
				$version
			);

			// Placeholder for script, if needed in the future for AJAX or dynamic interactions.
			// wp_enqueue_script(
			// 'commio-search-filter-script',
			// $plugin_base_url . 'assets/js/search-filter.js',
			// array( 'jquery' ),
			// $version,
			// true
			// );
		}

		// More methods will be added here for:
		// - Handling search queries.
		// - Displaying results.
		// - Retrieving filter options (taxonomies, custom fields, etc.).


		/**
		 * Handles the search form submission, builds, and executes WP_Query.
		 *
		 * @param array $atts Shortcode attributes.
		 */
		protected function handle_search_submission_and_get_results( $atts ) {
			$query_args = array(
				'post_status'    => 'publish',
				'posts_per_page' => get_option( 'posts_per_page', 10 ), // Standard pagination
				'paged'          => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
			);

			// Keyword search
			if ( ! empty( $_GET['s'] ) ) {
				$query_args['s'] = sanitize_text_field( $_GET['s'] );
			}

			// Post Type filtering
			$selected_post_types = array();
			if ( ! empty( $_GET['filter_post_type'] ) && is_array( $_GET['filter_post_type'] ) ) {
				$selected_post_types = array_map( 'sanitize_text_field', $_GET['filter_post_type'] );
			} elseif ( ! empty( $atts['post_types'] ) && $atts['post_types'] !== 'any' ) {
				$selected_post_types = array_map( 'trim', explode( ',', $atts['post_types'] ) );
			}

			if ( ! empty( $selected_post_types ) ) {
				$query_args['post_type'] = $selected_post_types;
			} else {
				// Default to 'post' or 'any' if nothing is specified via GET or shortcode atts for safety
				$query_args['post_type'] = !empty($atts['post_types']) ? $atts['post_types'] : 'any';
			}


			// Taxonomy filtering (categories, tags, custom taxonomies)
			$query_args['tax_query'] = array( 'relation' => 'AND' );

			$active_filters = ! empty( $atts['show_filters'] ) ? array_map( 'trim', explode( ',', $atts['show_filters'] ) ) : array();
			$taxonomies_to_check = array();
			if (in_array('categories', $active_filters)) $taxonomies_to_check[] = 'category';
			if (in_array('tags', $active_filters)) $taxonomies_to_check[] = 'post_tag';
			// Add other potential taxonomies if they become part of show_filters logic

			foreach ( $taxonomies_to_check as $taxonomy_slug ) {
				if ( ! empty( $_GET[ 'filter_tax_' . $taxonomy_slug ] ) && is_array( $_GET[ 'filter_tax_' . $taxonomy_slug ] ) ) {
					$term_ids = array_map( 'intval', $_GET[ 'filter_tax_' . $taxonomy_slug ] );
					if ( ! empty( $term_ids ) ) {
						$query_args['tax_query'][] = array(
							'taxonomy' => $taxonomy_slug,
							'field'    => 'term_id',
							'terms'    => $term_ids,
							'operator' => 'IN',
						);
					}
				}
			}

			if ( count( $query_args['tax_query'] ) <= 1 ) { // Only 'relation' => 'AND'
				unset( $query_args['tax_query'] );
			}

			// TODO: Add filters for Authors, Date Range, Custom Fields here
			// Example for Authors (if 'filter_author' is a GET param with user ID)
			// if ( ! empty( $_GET['filter_author'] ) ) {
			//    $query_args['author'] = intval( $_GET['filter_author'] );
			// }

			// Example for Date Range
			// if ( ! empty( $_GET['date_after'] ) && ! empty( $_GET['date_before'] ) ) {
			//     $query_args['date_query'] = array(
			//         array(
			//             'after'     => sanitize_text_field($_GET['date_after']),
			//             'before'    => sanitize_text_field($_GET['date_before']),
			//             'inclusive' => true,
			//         ),
			//     );
			// }

			// Example for Custom Fields (based on 'custom_fields' shortcode attribute)
			// $shortcode_custom_fields = !empty($atts['custom_fields']) ? array_map('trim', explode(',', $atts['custom_fields'])) : [];
			// if (!empty($shortcode_custom_fields)) {
			//     $query_args['meta_query'] = array('relation' => 'OR'); // Or 'AND'
			//     foreach ($shortcode_custom_fields as $meta_key) {
			//         if (!empty($_GET['filter_meta_' . $meta_key])) {
			//             $query_args['meta_query'][] = array(
			//                 'key' => $meta_key,
			//                 'value' => sanitize_text_field($_GET['filter_meta_' . $meta_key]),
			//                 'compare' => 'LIKE' // Or other comparisons
			//             );
			//         }
			//     }
			//     if (count($query_args['meta_query']) <= 1) {
			//         unset($query_args['meta_query']);
			//     }
			// }


			// Allow other plugins/themes to modify query args
			$query_args = apply_filters( 'commio_search_filter_query_args', $query_args, $atts, $_GET );

			// Execute the query
			$this->search_query = new WP_Query( $query_args );

			// Prevent main query from being overridden if this is the main query context (e.g. search results page)
			// Not strictly necessary here as we are using our own loop, but good practice.
			// if ( is_main_query() && isset( $_GET['commio_search'] ) ) {
			// wp_reset_query(); // This might be too aggressive.
			// }
		}

		/**
		 * Displays the search results.
		 *
		 * @param array $atts Shortcode attributes.
		 */
		protected function display_search_results( $atts ) {
			if ( ! $this->search_query || ! $this->search_query->have_posts() ) {
				echo '<div class="commio-search-no-results"><p>' . esc_html__( 'No results found for your criteria.', 'commio-engine' ) . '</p></div>';
				return;
			}

			echo '<div class="commio-search-results">';
			echo '<h3>' . esc_html__( 'Search Results', 'commio-engine' ) . '</h3>';
			echo '<ul>';

			while ( $this->search_query->have_posts() ) {
				$this->search_query->the_post();
				echo '<li>';
				echo '<h4><a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a></h4>';
				// Output excerpt or other post details as needed
				the_excerpt();
				echo '</li>';
			}
			echo '</ul>';

			// Pagination
			$this->render_pagination();

			echo '</div>'; // .commio-search-results

			wp_reset_postdata(); // Restore original post data.
		}

		/**
		 * Renders pagination for the search results.
		 */
		protected function render_pagination() {
			if ( $this->search_query && $this->search_query->max_num_pages > 1 ) {
				echo '<div class="commio-search-pagination">';
				$big = 999999999; // need an unlikely integer
				echo paginate_links( array(
					'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'  => '?paged=%#%',
					'current' => max( 1, get_query_var('paged') ),
					'total'   => $this->search_query->max_num_pages,
					'prev_text' => __('&laquo; Previous'),
					'next_text' => __('Next &raquo;'),
				) );
				echo '</div>';
			}
		}


	} // End class Commio_Engine_Search_Filter_Module.

	/**
	 * Initialize the module.
	 *
	 * Creates an instance of the Commio_Engine_Search_Filter_Module class.
	 */
	function commio_engine_search_filter_module_init() {
		new Commio_Engine_Search_Filter_Module();
	}
	add_action( 'plugins_loaded', 'commio_engine_search_filter_module_init', 20 );
	// Using priority 20 to ensure Commio Engine might have loaded its core functionalities.

} // End if class_exists check.

?>
