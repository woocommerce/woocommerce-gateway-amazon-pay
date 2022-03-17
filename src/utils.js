export const getBlocksConfiguration = ( name ) => {
	const amzPayServerData = wc.wcSettings.getSetting( name, null );

	if ( ! amzPayServerData ) {
		throw new Error( 'Amazon Pay initialization data is not available' );
	}

	return amzPayServerData;
};