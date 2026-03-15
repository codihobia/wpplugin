( function ( blocks, element, blockEditor, components, serverSideRender ) {
    var el             = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps   = blockEditor.useBlockProps;
    var PanelBody       = components.PanelBody;
    var SelectControl   = components.SelectControl;
    var TextControl     = components.TextControl;
    var ToggleControl   = components.ToggleControl;
    var Placeholder     = components.Placeholder;
    var ServerSideRender = serverSideRender;

    var templates = ( window.dsgBlockData && window.dsgBlockData.templates ) || [];

    blocks.registerBlockType( 'dsg/generator', {
        edit: function ( props ) {
            var attrs       = props.attributes;
            var setAttrs    = props.setAttributes;
            var blockProps  = useBlockProps();

            var templateOptions = templates.map( function ( t ) {
                return { value: String( t.value ), label: t.label };
            } );

            var selectedTemplate = templates.find( function ( t ) {
                return t.value === attrs.templateId;
            } );

            var inspectorPanel = el(
                InspectorControls,
                null,
                el(
                    PanelBody,
                    { title: '模板配置', initialOpen: true },
                    el( SelectControl, {
                        label: '提示词模板',
                        value: String( attrs.templateId ),
                        options: templateOptions,
                        onChange: function ( val ) {
                            setAttrs( { templateId: parseInt( val, 10 ) } );
                        },
                    } ),
                    el( TextControl, {
                        label: '输入框占位文本',
                        value: attrs.placeholder,
                        onChange: function ( val ) { setAttrs( { placeholder: val } ); },
                    } ),
                    el( TextControl, {
                        label: '按钮文字',
                        value: attrs.buttonText,
                        onChange: function ( val ) { setAttrs( { buttonText: val } ); },
                    } ),
                    el( ToggleControl, {
                        label: '显示输出样例',
                        checked: attrs.showExample,
                        onChange: function ( val ) { setAttrs( { showExample: val } ); },
                    } ),
                    el( SelectControl, {
                        label: '主题',
                        value: attrs.theme,
                        options: [
                            { value: 'light', label: '浅色' },
                            { value: 'dark',  label: '深色' },
                        ],
                        onChange: function ( val ) { setAttrs( { theme: val } ); },
                    } )
                )
            );

            var preview;
            if ( ! attrs.templateId ) {
                preview = el(
                    Placeholder,
                    {
                        icon: 'format-chat',
                        label: 'DeepSeek AI Generator',
                        instructions: '请在右侧面板选择一个提示词模板。',
                    }
                );
            } else {
                var templateName = selectedTemplate ? selectedTemplate.label : '模板 #' + attrs.templateId;
                var exampleText  = selectedTemplate && selectedTemplate.example ? selectedTemplate.example : '';

                preview = el(
                    'div',
                    { className: 'dsg-block-preview' },
                    el( 'div', { className: 'dsg-block-preview-header' },
                        el( 'span', { className: 'dashicons dashicons-format-chat' } ),
                        el( 'strong', null, ' ' + templateName )
                    ),
                    el( 'div', { className: 'dsg-block-preview-body' },
                        el( 'div', {
                            className: 'dsg-block-preview-input',
                            style: { padding: '10px', background: '#f9f9f9', borderRadius: '6px', color: '#999', marginBottom: '10px', border: '1px solid #ddd' },
                        }, attrs.placeholder ),
                        el( 'button', {
                            className: 'button button-primary',
                            disabled: true,
                            style: { marginBottom: '10px' },
                        }, attrs.buttonText ),
                        attrs.showExample && exampleText ?
                            el( 'div', {
                                style: { padding: '10px', background: '#f0f6fc', borderRadius: '6px', fontSize: '13px', color: '#555', borderLeft: '3px solid #2271b1', marginBottom: '10px' },
                            },
                                el( 'strong', null, '输出样例: ' ),
                                exampleText
                            ) : null,
                        ( selectedTemplate && ( selectedTemplate.allowSave || selectedTemplate.showHistory ) ) ?
                            el( 'div', {
                                style: { padding: '8px 10px', background: '#fef3c7', borderRadius: '6px', fontSize: '12px', color: '#92400e', display: 'flex', gap: '12px' },
                            },
                                selectedTemplate.allowSave ? el( 'span', null, '\u2705 允许保存' ) : null,
                                selectedTemplate.showHistory ? el( 'span', null, '\ud83d\udcdc 展示历史' ) : null
                            ) : null
                    )
                );
            }

            return el(
                'div',
                blockProps,
                inspectorPanel,
                preview
            );
        },

        save: function () {
            return null;
        },
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender
);
