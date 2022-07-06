mw.hook( 'postEdit' ).add( function ( $content ) {
	var title = mw.Title.newFromText( mw.config.get( 'wgPageName' ) );
	window.location.href = title.getUrl();
} );
