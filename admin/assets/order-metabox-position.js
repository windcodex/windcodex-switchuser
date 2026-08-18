(function () {
	function sgMoveOrderBox() {
		// Works for both classic shop_order and HPOS wc-orders screens.
		var ids = [ 'switchuser-order-switch', 'switchuser-order-switch-hpos' ];
		var box = null;
		for ( var i = 0; i < ids.length; i++ ) {
			box = document.getElementById( ids[ i ] );
			if ( box ) { break; }
		}
		if ( ! box ) { return; }
		var sidebar = document.getElementById( 'side-sortables' );
		if ( ! sidebar ) { return; }
		if ( sidebar.firstElementChild !== box ) {
			sidebar.insertBefore( box, sidebar.firstElementChild );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', sgMoveOrderBox );
	} else {
		sgMoveOrderBox();
	}
})();
