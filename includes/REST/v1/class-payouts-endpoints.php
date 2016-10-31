<?php
namespace AffWP\Affiliate\Payout\REST\v1;

use AffWP\REST\v1\Controller;

/**
 * Implements REST routes and endpoints for Payouts.
 *
 * @since 1.9
 *
 * @see AffWP\REST\Controller
 */
class Endpoints extends Controller {

	/**
	 * Route base for payouts.
	 *
	 * @access public
	 * @since  1.9
	 * @var    string
	 */
	public $rest_base = 'payouts';

	/**
	 * Registers Affiliate routes.
	 *
	 * @access public
	 * @since  1.9
	 */
	public function register_routes() {

		// /payouts/
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_items' ),
			'args'     => $this->get_collection_params(),
			'permission_callback' => function( $request ) {
				return current_user_can( 'manage_affiliates' );
			}
		) );

		// /payouts/ID
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_item' ),
			'args'     => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					}
				)
			),
			'permission_callback' => function( $request ) {
				return current_user_can( 'manage_affiliates' );
			}
		) );
	}

	/**
	 * Base endpoint to retrieve all payouts.
	 *
	 * @access public
	 * @since  1.9
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Payouts response object or \WP_Error object if not found.
	 */
	public function get_items( $request ) {

		$args = array();

		$args['number']         = isset( $request['number'] )         ? $request['number'] : 20;
		$args['offset']         = isset( $request['offset'] )         ? $request['offset'] : 0;
		$args['payout_id']      = isset( $request['payout_id'] )      ? $request['payout_id'] : 0;
		$args['affiliate_id']   = isset( $request['affiliate_id'] )   ? $request['affiliate_id'] : 0;
		$args['referrals']      = isset( $request['referrals'] )      ? $request['referrals'] : array();
		$args['amount']         = isset( $request['amount'] )         ? $request['amount'] : 0;
		$args['amount_compare'] = isset( $request['amount_compare'] ) ? $request['amount'] : '=';
		$args['owner']          = isset( $request['owner'] )          ? $request['owner'] : 0;
		$args['payout_method']  = isset( $request['payout_method'] )  ? $request['payout_method'] : '';
		$args['status']         = isset( $request['status'] )         ? $request['status'] : '';
		$args['date']           = isset( $request['date'] )           ? $request['date'] : '';
		$args['order']          = isset( $request['order'] )          ? $request['order'] : 'ASC';
		$args['orderby']        = isset( $request['orderby'] )        ? $request['orderby'] : '';
		$args['fields']         = isset( $request['fields'] )         ? $request['fields'] : '*';
		$args['search']         = isset( $request['search'] )         ? $request['search'] : false;

		if ( is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $request['filter'] );
		}

		/**
		 * Filters the query arguments used to retrieve payouts in a REST request.
		 *
		 * @since 1.9
		 *
		 * @param array            $args    Arguments.
		 * @param \WP_REST_Request $request Request.
		 */
		$args = apply_filters( 'affwp_rest_payouts_query_args', $args, $request );

		$payouts = affiliate_wp()->affiliates->payouts->get_payouts( $args );

		if ( empty( $payouts ) ) {
			$payouts = new \WP_Error(
				'no_payouts',
				'No payouts were found.',
				array( 'status' => 404 )
			);
		}

		return $this->response( $payouts );
	}

	/**
	 * Endpoint to retrieve a payout by ID.
	 *
	 * @access public
	 * @since  1.9
	 *
	 * @param \WP_REST_Request $request Request arguments.
	 * @return \WP_REST_Response|\WP_Error Payout object response or \WP_Error object if not found.
	 */
	public function get_item( $request ) {
		if ( ! $payout = \affwp_get_payout( $request['id'] ) ) {
			$payout = new \WP_Error(
				'invalid_payout_id',
				'Invalid payout ID',
				array( 'status' => 404 )
			);
		}

		return $this->response( $payout );
	}

	/**
	 * Retrieves the collection parameters for payouts.
	 *
	 * @access public
	 * @since  1.9
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		/*
		 * Pass top-level args as query vars:
		 * /payouts/?status=paid&order=desc
		 */
		$params['payout_id'] = array(
			'description'       => __( 'The payout ID or array of IDs to query for.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		$params['affiliate_id'] = array(
			'description'       => __( 'The affiliate ID or array of IDs to query payouts for.', 'affiliate-wp' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param );
			},
		);

		$params['referrals'] = array(
			'description'       => __( 'Referral ID or array of referral IDs to retrieve payouts for.', 'affiliate-wp' ),
			'sanitize_callback' => function( $param, $request, $key ) {
				return is_numeric( $param ) || is_array( $param );
			},
		);

		$params['amount'] = array(
			'description'       => __( 'Payout amount (float) or min/max range (array) to retrieve payouts for.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_float( $param ) || is_array( $param );
			},
		);

		$params['amount_compare'] = array(
			'description'       => __( "Comparison operator used with 'amount'. Accepts '>', '<', '>=', '<=', '=', or '!='.", 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return in_array( $param, array( '>', '<', '>=', '<=', '=', '!=' ) );
			},
		);

		$params['owner'] = array(
			'description'       => __( 'ID or array of IDs for users who generated payouts. Default empty.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_numeric( $param ) || is_array( $param );
			}
		);

		$params['status'] = array(
			'description'       => __( "The payout status. Accepts 'paid' or 'failed'.", 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return in_array( $param, array( 'paid', 'failed' ) );
			},
		);

		$params['orderby'] = array(
			'description'       => __( 'Payouts table column to order by.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return array_key_exists( $param, affiliate_wp()->affiliates->payouts->get_columns() );
			}
		);

		$params['date'] = array(
			'description'       => __( 'The date array or string to query payouts within.', 'affiliate-wp' ),
			'validate_callback' => function( $param, $request, $key ) {
				return is_string( $param ) || is_array( $param );
			},
		);

		/*
		 * Pass any valid get_payouts() args via filter:
		 * /payouts/?filter[status]=paid&filter[order]=desc
		 */
		$params['filter'] = array(
			'description' => __( 'Use any get_payouts() arguments to modify the response.', 'affiliate-wp' )
		);

		return $params;
	}
}
