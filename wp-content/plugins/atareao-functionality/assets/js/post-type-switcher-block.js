/* global atareaoPts, wp */
/**
 * Post Type Switcher — editor de bloques (JS nativo, sin build tools).
 *
 * Registra un plugin de WordPress que añade una fila «Post Type» dentro de
 * `PluginPostStatusInfo` con un `Dropdown` que contiene un fieldset con un
 * radio por cada tipo disponible. Al elegir un tipo distinto se pide
 * confirmación y, si se acepta, se navega a la URL de cambio.
 *
 * Los datos se inyectan desde PHP en `window.atareaoPts`:
 *   - currentPostType      : slug del tipo actual del post.
 *   - currentPostTypeLabel : etiqueta singular del tipo actual.
 *   - availablePostTypes   : [{ value, label }, ...].
 *   - changeUrl            : URL admin-ajax con action + nonce + post_id.
 */
(function () {
    'use strict';

    // Guarda de salida: sin datos ni las APIs que usamos, no hacemos nada.
    if (!window.atareaoPts || !window.wp || !window.wp.plugins ||
        !wp.element || !wp.components || !wp.i18n) {
        return;
    }

    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;
    var Dropdown = wp.components.Dropdown;
    var Button = wp.components.Button;
    var PluginPostStatusInfo =
        (wp.editor && wp.editor.PluginPostStatusInfo) ||
        (wp.editPost && wp.editPost.PluginPostStatusInfo);

    // Sin el componente de estado no podemos registrar la fila. No puede ir en
    // la guarda inicial: `var` está hoisted como undefined y saldría siempre.
    if (!PluginPostStatusInfo) {
        return;
    }

    /**
     * Formulario con la lista de tipos conmutables.
     *
     * @return {Object} Elemento React.
     */
    function PostTypeSwitcherForm() {
        var state = useState(window.atareaoPts.currentPostType);
        var currentPostType = state[0];
        var setCurrentPostType = state[1];

        if (!Array.isArray(window.atareaoPts.availablePostTypes)) {
            return null;
        }

        return el(
            'fieldset',
            {
                key: 'atareao-post-type-switcher-selector',
                className: 'editor-post-type__dialog-fieldset'
            },
            el(
                'legend',
                { className: 'editor-post-type__dialog-legend' },
                __('Post Type', 'atareao-functionality')
            ),
            window.atareaoPts.availablePostTypes.map(function (postType) {
                var value = postType.value;
                var label = postType.label;

                return el(
                    'div',
                    { key: value, className: 'editor-post-type__choice' },
                    el('input', {
                        type: 'radio',
                        className: 'editor-post-type__dialog-radio',
                        name: 'editor-post-type__setting',
                        id: 'editor-post-type-switcher-' + value,
                        value: value,
                        checked: value === currentPostType,
                        onChange: function () {
                            var oldPostType = currentPostType;

                            setCurrentPostType(value);

                            var message = sprintf(
                                __(
                                    "Are you sure you want to change this from a '%s' to a '%s'?",
                                    'atareao-functionality'
                                ),
                                oldPostType,
                                value
                            );

                            if (window.confirm(message)) {
                                window.location.href =
                                    window.atareaoPts.changeUrl +
                                    '&pts_post_type=' +
                                    value;
                            } else {
                                setCurrentPostType(oldPostType);
                            }
                        }
                    }),
                    el(
                        'label',
                        {
                            htmlFor: 'editor-post-type-switcher-' + value,
                            className: 'editor-post-type__dialog-label'
                        },
                        label
                    )
                );
            })
        );
    }

    /**
     * Fila «Post Type» dentro del panel de estado de la entrada.
     *
     * @return {Object} Elemento React.
     */
    function PostTypeSwitcher() {
        return el(
            PluginPostStatusInfo,
            null,
            el(
                'div',
                { className: 'edit-post-post-type' },
                el(
                    'div',
                    { className: 'editor-post-panel__row-label' },
                    __('Post Type', 'atareao-functionality')
                ),
                el(
                    'div',
                    { className: 'editor-post-panel__row-control' },
                    el(Dropdown, {
                        popoverProps: {
                            placement: 'left-start',
                            offset: 138,
                            shift: true
                        },
                        contentClassName: 'edit-post-post-type__dialog',
                        renderToggle: function (props) {
                            return el(
                                Button,
                                {
                                    type: 'button',
                                    'aria-expanded': props.isOpen,
                                    'aria-label': sprintf(
                                        __(
                                            'Change post type: %s',
                                            'atareao-functionality'
                                        ),
                                        window.atareaoPts.currentPostType
                                    ),
                                    className:
                                        'edit-post-post-type__toggle is-compact is-tertiary',
                                    onClick: props.onToggle
                                },
                                window.atareaoPts.currentPostTypeLabel
                            );
                        },
                        renderContent: function () {
                            return el(PostTypeSwitcherForm);
                        }
                    })
                )
            )
        );
    }

    wp.plugins.registerPlugin('atareao-post-type-switcher', {
        render: PostTypeSwitcher
    });
})();
