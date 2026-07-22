/**
 * Bloque de OpenGist
 */
(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, SelectControl } = wp.components;
    const { __ } = wp.i18n;
    const { createElement: el } = wp.element;
    const { useSelect } = wp.data;

    registerBlockType('atareao/opengist', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { server, username, gistId, file, theme } = attributes;
            const blockProps = useBlockProps();

            // Obtener valores por defecto de las opciones del plugin
            const defaults = useSelect(function (select) {
                return select('core').getEntityRecord('root', 'site');
            }, []);

            const defaultServer = defaults && defaults.atareao_opengist_server
                ? defaults.atareao_opengist_server
                : '';
            const defaultUsername = defaults && defaults.atareao_opengist_username
                ? defaults.atareao_opengist_username
                : '';

            // Usar el valor actual o el defecto
            const currentServer = server || defaultServer;
            const currentUsername = username || defaultUsername;

            const themeOptions = [
                { label: __('Auto', 'atareao-functionality'), value: 'auto' },
                { label: __('Claro', 'atareao-functionality'), value: 'light' },
                { label: __('Oscuro', 'atareao-functionality'), value: 'dark' }
            ];

            // Vista previa en el editor
            const hasAllData = currentServer && currentUsername && gistId;
            const embedUrl = hasAllData
                ? currentServer.replace(/\/+$/, '') + '/' + currentUsername + '/' + gistId + '.js'
                : null;

            const previewGistId = gistId || __('(sin ID)', 'atareao-functionality');
            const previewServer = currentServer || __('(servidor por defecto)', 'atareao-functionality');
            const previewUsername = currentUsername || __('(usuario por defecto)', 'atareao-functionality');

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        {
                            title: __('Configuración del Gist', 'atareao-functionality'),
                            initialOpen: true
                        },
                        el(TextControl, {
                            label: __('Servidor', 'atareao-functionality'),
                            value: server,
                            onChange: function (value) {
                                setAttributes({ server: value });
                            },
                            placeholder: defaultServer || 'https://gist.atareao.es',
                            help: __('Dirección del servidor OpenGist. Vacío para usar el valor por defecto.', 'atareao-functionality')
                        }),
                        el(TextControl, {
                            label: __('Usuario', 'atareao-functionality'),
                            value: username,
                            onChange: function (value) {
                                setAttributes({ username: value });
                            },
                            placeholder: defaultUsername || 'atareao',
                            help: __('Nombre de usuario en OpenGist. Vacío para usar el valor por defecto.', 'atareao-functionality')
                        }),
                        el(TextControl, {
                            label: __('ID del Gist', 'atareao-functionality'),
                            value: gistId,
                            onChange: function (value) {
                                setAttributes({ gistId: value });
                            },
                            placeholder: 'f64d17b337ea4cfb973855af42c2c61d',
                            help: __('Identificador único del gist', 'atareao-functionality')
                        }),
                        el(TextControl, {
                            label: __('Archivo (opcional)', 'atareao-functionality'),
                            value: file,
                            onChange: function (value) {
                                setAttributes({ file: value });
                            },
                            placeholder: 'ejemplo.py',
                            help: __('Nombre del archivo a mostrar si el gist tiene varios', 'atareao-functionality')
                        }),
                        el(SelectControl, {
                            label: __('Tema', 'atareao-functionality'),
                            value: theme,
                            options: themeOptions,
                            onChange: function (value) {
                                setAttributes({ theme: value });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    { className: 'atareao-opengist-editor' },
                    el(
                        'div',
                        { className: 'opengist-icon' },
                        el('span', { className: 'dashicons dashicons-editor-code' })
                    ),
                    el(
                        'div',
                        { className: 'opengist-preview' },
                        embedUrl
                            ? el(
                                'div',
                                { className: 'opengist-embed-notice' },
                                el(
                                    'p',
                                    {},
                                    __('🌐 Vista previa no disponible en el editor. El gist se renderizará en la página publicada.', 'atareao-functionality')
                                ),
                                el(
                                    'code',
                                    { className: 'opengist-url' },
                                    embedUrl
                                )
                            )
                            : el(
                                'div',
                                { className: 'opengist-placeholder' },
                                el(
                                    'p',
                                    {},
                                    __('👈 Configura el gist en el panel de la derecha', 'atareao-functionality')
                                )
                            )
                    ),
                    el(
                        'div',
                        { className: 'opengist-info' },
                        el(
                            'small',
                            {},
                            __('Servidor:', 'atareao-functionality') + ' ' + previewServer + ' | ' +
                            __('Usuario:', 'atareao-functionality') + ' ' + previewUsername + ' | ' +
                            __('ID:', 'atareao-functionality') + ' ' + previewGistId
                        )
                    )
                )
            );
        },

        save: function () {
            // Renderizado dinámico en PHP
            return null;
        }
    });
})(window.wp);