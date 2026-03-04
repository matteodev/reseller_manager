( function( blocks, blockEditor, i18n, element ) {
    const { registerBlockType } = blocks;
    const { useBlockProps } = blockEditor;
    const { __ } = i18n;

    registerBlockType( 'mc-reseller-manager/reseller-login', {
        edit: () => {
            const blockProps = useBlockProps({ className: 'mpwp-reseller-block-placeholder' });
            return element.createElement(
                'div',
                blockProps,
                element.createElement('strong', null, __('Login Rivenditore', 'mc-reseller-manager') ),
                element.createElement('p', null, __('Mostra il form di accesso rivenditori.', 'mc-reseller-manager') )
            );
        },
        save: () => null,
    } );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.i18n, window.wp.element );
