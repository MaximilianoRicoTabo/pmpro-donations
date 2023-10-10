<?php
/**
 * Update donation amount if a dropdown value is used
 */
function pmprodon_init_dropdown_values() {

	if ( ! empty( $_REQUEST['donation_dropdown'] ) && $_REQUEST['donation_dropdown'] != 'other' ) {
		$_REQUEST['donation'] = $_REQUEST['donation_dropdown'];
	}

	if ( ! empty( $_GET['donation_dropdown'] ) && $_GET['donation_dropdown'] != 'other' ) {
		$_GET['donation'] = $_GET['donation_dropdown'];
	}

	if ( ! empty( $_POST['donation_dropdown'] ) && $_POST['donation_dropdown'] != 'other' ) {
		$_POST['donation'] = $_POST['donation_dropdown'];
	}
}
add_action( 'pmpro_checkout_preheader_before_get_level_at_checkout', 'pmprodon_init_dropdown_values', 1 );

/**
 * Show form at checkout.
 */
function pmprodon_pmpro_checkout_after_level_cost() {
	global $pmpro_currency_symbol, $pmpro_level, $gateway, $pmpro_review;

	// get variable pricing info
	$donfields = get_option( 'pmprodon_' . $pmpro_level->id );

	// no variable pricing? just return
	if ( empty( $donfields ) || empty( $donfields['donations'] ) ) {
		return;
	}

	// okay, now we're showing the form
	$min_price       = $donfields['min_price'];
	$max_price       = $donfields['max_price'];
	$dropdown_prices = $donfields['dropdown_prices'];
	//We want to keep backwards compatibility with those donations options that doest't have saved the placeholder
	$donation_placeholder = $donfields['donation_placeholder'] ? $donfields['donation_placeholder'] : "";


	if ( isset( $_REQUEST['donation'] ) ) {
		$donation = preg_replace( '/[^0-9\.]/', '', $_REQUEST['donation'] );
	} elseif ( ! empty( $min_price ) ) {
		$donation = $min_price;
	} else {
		$donation = '';
	}

	?>
	<hr />
	<div id="pmpro_donations">
		
		<span id="pmprodon_donation_prompt"><?php _e( 'Make a Gift', 'pmpro-donations' ); ?></span>
		
	<?php
	// check for dropdown
	if ( ! empty( $dropdown_prices ) ) {
		// turn into an array
		$dropdown_prices = str_replace( ' ', '', $dropdown_prices );
		$dropdown_prices = explode( ',', $dropdown_prices );

		// check for other option
		$pmprodon_allow_other = array_search( 'other', $dropdown_prices );
		if ( $pmprodon_allow_other !== false ) {
			unset( $dropdown_prices[ $pmprodon_allow_other ] );
			$pmprodon_allow_other = true;
		}

		// show dropdown
		sort( $dropdown_prices );
		?>
		<select id="donation_dropdown" name="donation_dropdown" <?php if ( $pmpro_review ) { ?>disabled="disabled"<?php } ?>>
			<?php
			foreach ( $dropdown_prices as $price ) {
				?>
				<option <?php selected( $price, $donation ); ?> value="<?php echo esc_attr( $price ); ?>"><?php echo pmpro_formatPrice( (double) $price ); ?></option>
				<?php
			}
			if ( $pmprodon_allow_other ) {
				?>
				<option value="other" <?php selected( true, ! empty( $donation ) && ! in_array( $donation, $dropdown_prices ) ); ?>>Other</option>
			<?php } ?>
		</select> &nbsp;
		<?php
	}
	?>

	<span id="pmprodon_donation_input" <?php if ( ! empty( $pmprodon_allow_other ) && ( empty( $_REQUEST['donation_dropdown'] ) || $_REQUEST['donation_dropdown'] != 'other' ) ) { ?>style="display: none;"<?php } ?>>
		<?php echo $pmpro_currency_symbol; ?> <input autocomplete="off" type="number" step="0.01" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" placeholder="<?php echo $donation_placeholder; ?>" id="donation" name="donation" size="10" <?php if ( $pmpro_review ) { ?>disabled="disabled"<?php } ?> />

		<?php if ( $pmpro_review ) { ?>
			<input type="hidden" name="donation" value="<?php echo esc_attr( $donation ); ?>" />
		<?php } ?>
	</span>

	<?php
	if ( empty( $pmpro_review ) ) {
		?>
		<div class="pmpro_small">
		<?php
		if ( ! empty( $donfields['text'] ) ) {
			echo wpautop( $donfields['text'] );
		} elseif ( ! empty( $donfields['min_price'] ) && empty( $donfields['max_price'] ) ) {
			printf( __( 'Enter an amount %s or greater', 'pmpro-donations' ), pmpro_formatPrice( $donfields['min_price'] ) );
		} elseif ( ! empty( $donfields['max_price'] ) && empty( $donfields['min_price'] ) ) {
			printf( __( 'Enter an amount %s or less', 'pmpro-donations' ), pmpro_formatPrice( $donfields['max_price'] ) );
		} elseif ( ! empty( $donfields['max_price'] ) && ! empty( $donfields['min_price'] ) ) {
			printf( __( 'Enter an amount between %1$s and %2$s', 'pmpro-donations' ), pmpro_formatPrice( $donfields['min_price'] ), pmpro_formatPrice( $donfields['max_price'] ) );
		}
		?>
		</div>
		<?php
	}
	?>
</div>
<script>
	//some vars for keeping track of whether or not we show billing
	const pmpro_gateway_billing = <?php if ( in_array( $gateway, array( 'paypalexpress', 'twocheckout' ) ) !== false ) { echo'false';	} else { echo 'true'; } ?>;
	const pmpro_pricing_billing = pmpro_donation_billing = <?php if ( ! pmpro_isLevelFree( $pmpro_level ) ) { echo 'true';	} else { echo 'false'; } ?>;

//this script will hide show billing fields based on the price set
	jQuery(document).ready(function($) {
		//Watch for donation dropdown changes
		$('#donation_dropdown').on('change', () => {
			pmprodon_toggleOther();
			pmprodon_checkForFree();
		});
		//Watch fot donation field changes
		$('#donation').on('keyup change', () => {
			pmprodon_checkForFree();
		});
		// Watch for gateway selection
		$('input[name=gateway]').on('click', () => {
			pmprodon_checkForFree();
		});

		//check when page loads too
		pmprodon_toggleOther();
		pmprodon_checkForFree();
	});

	/**
	 * Toggle donation input based on dropdown selection.
	 *
	 * @since TBD
	 */
	const pmprodon_toggleOther = () => {
		//make sure there is a dropdown to check
		if(!jQuery('#donation_dropdown').length) {
			return;
		}

		//check if "other" option is selected
		const isOther = jQuery('#donation_dropdown').val() == 'other';
		jQuery('#pmprodon_donation_input').toggle(isOther);
		if(!isOther) {
			jQuery('#donation').val(jQuery('#donation_dropdown').val());
		}
	};

	/**
	 * Toggle billing fields based on donation, level pricing and gateway.
	 *
	 * @since TBD
	 */
	const pmprodon_checkForFree = () => {
		const donation = parseFloat(jQuery('#donation').val());
		//does the gateway require billing?
		if(jQuery('input[name=gateway]').length) {
			const no_billing_gateways = ['paypalexpress', 'twocheckout', 'check', 'paypalstandard'];
			var gateway = jQuery('input[name=gateway]:checked').val();
			//determine if there's a checked gateway
			const pmpro_gateway_billing = ! no_billing_gateways.includes(gateway);
		}

		//figure out if we should show the billing fields
		if(donation || (pmpro_gateway_billing && pmpro_pricing_billing)) {
			toggleBillingFields(true);
		} else if ( 'check' !== gateway ) {
			toggleBillingFields(false);
		}
	};

	/**
	 * Toggle billing fields.
	 *
	 * @since TBD
	 */
	const toggleBillingFields = toggle => {
		jQuery('#pmpro_billing_address_fields').toggle(toggle)
		jQuery('#pmpro_payment_information_fields').toggle(toggle);
		pmpro_require_billing = toggle;
	}
</script>
<?php
}
add_action( 'pmpro_checkout_after_level_cost', 'pmprodon_pmpro_checkout_after_level_cost' );

/**
 * Set price at checkout
 */
function pmprodon_pmpro_checkout_level( $level ) {

	if ( isset( $_REQUEST['donation'] ) ) {
		$donation = preg_replace( '/[^0-9\.]/', '', $_REQUEST['donation'] );
	} else {
		return $level;
	}

	if ( ! empty( $donation ) && $donation > 0 ) {
		// save initial payment amount
		global $pmprodon_original_initial_payment;
		$pmprodon_original_initial_payment = $level->initial_payment;

		// add donation
		$level->initial_payment = $level->initial_payment + $donation;
	}

	return $level;
}
add_filter( 'pmpro_checkout_level', 'pmprodon_pmpro_checkout_level', 99 );

/**
 * Check price is between min and max.
 */
function pmprodon_pmpro_registration_checks( $continue ) {
	// only bother if we are continuing already
	if ( $continue ) {
		global $pmpro_currency_symbol, $pmpro_msg, $pmpro_msgt;

		// was a donation passed in?
		if ( isset( $_REQUEST['donation'] ) ) {
			// get values
			$level = pmpro_getLevelAtCheckout();
			$donfields = get_option( 'pmprodon_' . $level->id );

			// make sure this level has variable pricing
			if ( empty( $donfields ) || empty( $donfields['donations'] ) ) {
				$pmpro_msg  = __( "Error: You tried to set the donation on a level that doesn't have donations. Please try again.", 'pmpro-donations' );
				$pmpro_msgt = 'pmpro_error';
			}

			// get price
			$donation = preg_replace( '/[^0-9\.]/', '', $_REQUEST['donation'] );

			// check that the donation falls between the min and max
			if ( (double) $donation < 0 || ( ! empty( $donfields['min_price'] ) && (double) $donation < (double) $donfields['min_price'] ) ) {
				$pmpro_msg  = sprintf( __( 'The lowest accepted donation is %s. Please enter a new amount.', 'pmpro-donations' ), pmpro_formatPrice( $donfields['min_price'] ) );
				$pmpro_msgt = 'pmpro_error';
				$continue   = false;
			} elseif ( ! empty( $donfields['max_price'] ) && (double) $donation > (double) $donfields['max_price'] ) {
				$pmpro_msg = sprintf( __( 'The highest accepted donation is %s. Please enter a new amount.', 'pmpro-donations' ), pmpro_formatPrice( $donfields['max_price'] ) );

				$pmpro_msgt = 'pmpro_error';
				$continue   = false;
			}

			// all good!
		}
	}

	return $continue;
}
add_filter( 'pmpro_registration_checks', 'pmprodon_pmpro_registration_checks' );

/**
 * Override level cost text on checkout page
 */
function pmprodon_pmpro_level_cost_text( $text, $level ) {
	global $pmprodon_original_initial_payment;
	if ( ! empty( $pmprodon_original_initial_payment ) ) {
		$olevel                  = clone $level;
		$olevel->initial_payment = $pmprodon_original_initial_payment;
		remove_filter( 'pmpro_level_cost_text', 'pmprodon_pmpro_level_cost_text', 10, 2);
		$text = pmpro_getLevelCost( $olevel );
		add_filter( 'pmpro_level_cost_text', 'pmprodon_pmpro_level_cost_text', 10, 2);
	}

	return $text;
}

/**
 * We only want pmprodon_pmpro_level_cost_text to run for the level cost on the checkout form.
 *
 * This means we want to hook on pmpro_checkout_before_form and unhook on pmpro_checkout_after_level_cost.
 */
function pmprodon_hook_pmpro_level_cost_text() {
	add_filter( 'pmpro_level_cost_text', 'pmprodon_pmpro_level_cost_text', 10, 2 );
}
add_action( 'pmpro_checkout_before_form', 'pmprodon_hook_pmpro_level_cost_text' );
function pmprodon_unhook_pmpro_level_cost_text() {
	remove_filter( 'pmpro_level_cost_text', 'pmprodon_pmpro_level_cost_text', 10, 2 );
}
add_action( 'pmpro_checkout_after_level_cost', 'pmprodon_unhook_pmpro_level_cost_text' );


/**
 * Save donation amount to order notes.
 */
function pmprodon_pmpro_checkout_order( $order ) {
	if ( ! empty( $_REQUEST['donation'] ) ) {
		$donation = preg_replace( '/[^0-9\.]/', '', $_REQUEST['donation'] );
	} else {
		return $order;
	}

	if ( empty( $order->notes ) ) {
		$order->notes = '';
	}

	if ( ! empty( $donation ) && strpos( $order->notes, __( 'Donation', 'pmpro-donations' ) ) === false ) {
		$order->notes .= __( 'Donation', 'pmpro-donations' ) . ': ' . $donation . "\n";
	}
	return $order;
}
add_filter( 'pmpro_checkout_order', 'pmprodon_pmpro_checkout_order' );

/**
 * Show order components on confirmation and invoice pages.
 */
function pmprodon_pmpro_invoice_bullets_bottom( $order ) {
	$components = pmprodon_get_price_components( $order );
	if ( ! empty( $components['donation'] ) ) {
		$bullets = array(
			'membership_cost' => '<strong>' . __( 'Membership Cost', 'pmpro-donations' ) . ": </strong> " . pmpro_formatPrice( $components['price'] ),
			'donation'        => '<strong>' . __( 'Donation', 'pmpro-donations' ) . ": </strong>" . pmpro_formatPrice( $components['donation'] )
		);
		apply_filters( 'pmpro_donations_invoice_bullets', $bullets, $order );
		foreach ( $bullets as $bullet ) {
			echo '<li>' . $bullet . '</li>';
		}
	}
}
add_filter( 'pmpro_invoice_bullets_bottom', 'pmprodon_pmpro_invoice_bullets_bottom' );

function pmprodon_pmpro_email_data( $data, $email ) {
	$order_id = empty( $email->data['invoice_id'] ) ? false : $email->data['invoice_id'];
	if ( ! empty( $order_id ) ) {
		$order      = new MemberOrder( $order_id );
		$components = pmprodon_get_price_components( $order );

		if ( ! empty( $components['donation'] ) ) {
			$data['donation'] =  pmpro_formatPrice( $components['donation'] );
		} else {
			$data['donation'] =  pmpro_formatPrice( 0 );
		}
	}
	return $data;
}
add_filter( 'pmpro_email_data', 'pmprodon_pmpro_email_data', 10, 2 );

/**
 * Show order components in confirmation email.
 */
function pmprodon_pmpro_email_filter( $email ) {
	global $wpdb;

	// only update confirmation emails which aren't using !!donation!! email variable
	if ( strpos( $email->template, 'checkout' ) !== false && strpos( $email->body, '!!donation!!' ) === false ) {
		// get the user_id from the email
		$order_id = ( empty( $email->data ) || empty( $email->data['invoice_id'] ) ) ? false : $email->data['invoice_id'];
		if ( ! empty( $order_id ) ) {
			$order      = new MemberOrder( $order_id );
			$components = pmprodon_get_price_components( $order );

			// add to bottom of email
			if ( ! empty( $components['donation'] ) ) {
				$email->body = preg_replace( '/\<p\>\s*' . __( 'Invoice', 'pmpro-donations' ) . '/', '<p>' . __( 'Donation Amount:', 'pmpro-donations' ) . '' . pmpro_formatPrice( $components['donation'] ) . '</p><p>' . __( 'Invoice', 'pmpro-donations' ), $email->body );
			}
		}
	}

	return $email;
}
add_filter( 'pmpro_email_filter', 'pmprodon_pmpro_email_filter', 10, 2 );

/**
 * If checking out for a level with donations, use SSL even if it's free
 *
 * @since .4
 */
function pmprodon_pmpro_checkout_preheader() {
	global $besecure;

	$level = pmpro_getLevelAtCheckout();
	if ( ! is_admin() && ! empty( $level->id ) ) {
		$donfields = get_option(
			'pmprodon_' . intval( $level->id ), array(
				'donations'       => 0,
				'min_price'       => '',
				'max_price'       => '',
				'dropdown_prices' => '',
				'text'            => '',
			)
		);

		if ( ! empty( $donfields ) && ! empty( $donfields['donations'] ) ) {
			$besecure = get_option( 'pmpro_use_ssl' );
		}
	}
}
add_action( 'pmpro_checkout_preheader', 'pmprodon_pmpro_checkout_preheader' );

/**
 * Fix issue where incorrect donation amount is charged when using PayPal Express.
 *
 * @since 1.1.3
 */
function pmprodon_ppe_add_donation_to_request() {
	// Check if the "review" or "confirm" request variables are set.
	if ( empty( $_REQUEST['review'] ) && empty( $_REQUEST['confirm'] ) ) {
		return;
	}

	// Check if we have a PPE token that we are reviewing.
	if ( empty( $_REQUEST['token'] ) ) {
		return;
	}
	$token = sanitize_text_field( $_REQUEST['token'] );

	// Make sure that the MemberOrder class is loaded.
	if ( ! class_exists( 'MemberOrder' ) ) {
		return;
	}

	// Check if we have an order with this token.
	$order = new MemberOrder();
	$order->getMemberOrderByPayPalToken( $token );
	if ( empty( $order->id ) ) {
		return;
	}

	// Make sure that this order is in token status.
	if ( $order->status !== 'token' ) {
		return;
	}

	// Get the donation information for this order.
	$donation = pmprodon_get_price_components( $order );

	// If there is a donation amount on the order but not yet in $_REQUEST, add it.
	if ( ! empty( $donation['donation'] ) && empty( $_REQUEST['donation'] ) ) {
		$_REQUEST['donation'] = $donation['donation'];
	}
}
add_action( 'pmpro_checkout_preheader_before_get_level_at_checkout', 'pmprodon_ppe_add_donation_to_request' );
