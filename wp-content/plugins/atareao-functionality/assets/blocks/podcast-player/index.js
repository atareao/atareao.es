/**
 * Bloque de Reproductor de Podcast
 */
(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, TextControl, TextareaControl, SelectControl } = wp.components;
    const { __ } = wp.i18n;
    const { createElement: el } = wp.element;
    const { useSelect } = wp.data;

    registerBlockType('atareao/podcast-player', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { audioUrl, title, description, podcastId } = attributes;
            const blockProps = useBlockProps();

            // Obtener lista de podcasts
            const podcasts = useSelect((select) => {
                return select('core').getEntityRecords('postType', 'podcast', {
                    per_page: -1,
                    status: 'publish'
                });
            }, []);

            // Opciones para el selector
            const podcastOptions = [
                { label: __('Seleccionar podcast...', 'atareao-functionality'), value: 0 }
            ];

            if (podcasts) {
                podcasts.forEach((podcast) => {
                    podcastOptions.push({
                        label: podcast.title.rendered,
                        value: podcast.id
                    });
                });
            }

            // Cuando se selecciona un podcast, cargar sus datos
            const loadPodcastData = (selectedId) => {
                if (selectedId > 0 && podcasts) {
                    const podcast = podcasts.find(p => p.id === selectedId);
                    if (podcast) {
                        setAttributes({
                            podcastId: selectedId,
                            title: title || podcast.title.rendered,
                            description: description || podcast.excerpt.rendered.replace(/<[^>]*>/g, '')
                        });

                        // Obtener la URL de audio desde los metadatos (campo mp3-url)
                        wp.apiFetch({
                            path: `/wp/v2/podcast/${selectedId}?_fields=meta`
                        }).then((data) => {
                            if (data.meta && data.meta['mp3-url']) {
                                setAttributes({
                                    audioUrl: audioUrl || data.meta['mp3-url']
                                });
                            }
                        });
                    }
                }
            };

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('Configuración del Podcast', 'atareao-functionality'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('Seleccionar Podcast', 'atareao-functionality'),
                            value: podcastId,
                            options: podcastOptions,
                            onChange: function(value) {
                                const id = parseInt(value);
                                setAttributes({ podcastId: id });
                                loadPodcastData(id);
                            },
                            help: __('Selecciona un podcast existente o ingresa datos manualmente', 'atareao-functionality')
                        }),
                        el(TextControl, {
                            label: __('URL del Audio', 'atareao-functionality'),
                            value: audioUrl,
                            onChange: function(value) { setAttributes({ audioUrl: value }); },
                            placeholder: 'https://ejemplo.com/audio.mp3',
                            help: __('URL directa del archivo de audio MP3', 'atareao-functionality')
                        }),
                        el(TextControl, {
                            label: __('Título', 'atareao-functionality'),
                            value: title,
                            onChange: function(value) { setAttributes({ title: value }); },
                            placeholder: __('Título del episodio', 'atareao-functionality')
                        }),
                        el(TextareaControl, {
                            label: __('Descripción', 'atareao-functionality'),
                            value: description,
                            onChange: function(value) { setAttributes({ description: value }); },
                            placeholder: __('Breve descripción del episodio', 'atareao-functionality'),
                            rows: 4
                        })
                    )
                ),
                el('div', { className: 'atareao-podcast-player-editor' },
                    el('div', { className: 'podcast-player-icon' },
                        el('span', { className: 'dashicons dashicons-format-audio' })
                    ),
                    title && el('h3', { className: 'podcast-player-title' }, title),
                    description && el('p', { className: 'podcast-player-description' }, description),
                    audioUrl ? 
                        el('div', { className: 'podcast-player-controls' },
                            el('audio', {
                                controls: true,
                                preload: 'metadata',
                                className: 'podcast-audio'
                            },
                                el('source', { src: audioUrl, type: 'audio/mpeg' }),
                                __('Tu navegador no soporta el elemento de audio.', 'atareao-functionality')
                            )
                        ) :
                        el('div', { className: 'podcast-player-placeholder' },
                            el('p', {}, __('👈 Configura el reproductor en el panel de la derecha', 'atareao-functionality'))
                        ),
                    podcastId > 0 && el('div', { className: 'podcast-player-info' },
                        el('small', {},
                            __('Podcast ID:', 'atareao-functionality') + ' ' + podcastId
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
