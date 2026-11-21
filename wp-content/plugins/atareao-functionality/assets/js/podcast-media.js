/*
 * Selector de multimedia del episodio.
 *
 * Cada botón `.atareao-media-select` abre la biblioteca de medios y escribe el
 * ID del adjunto elegido en el campo oculto indicado por `data-atareao-target`.
 * La imagen filtra la biblioteca por `image`; los capítulos aceptan cualquier
 * tipo porque el validador del guardado comprueba el contenido JSON.
 *
 * Todo es opcional: si `wp.media` no está disponible (por ejemplo, en una
 * pantalla sin la biblioteca encolada) los botones no hacen nada y el campo
 * oculto conserva el valor que tuviera.
 */
( function () {
    'use strict';

    /**
     * Devuelve el campo oculto asociado a un botón.
     *
     * @param {HTMLElement} button Botón pulsado.
     * @return {HTMLInputElement|null}
     */
    function targetField( button ) {
        var id = button.getAttribute( 'data-atareao-target' );
        if ( ! id ) {
            return null;
        }

        var field = document.getElementById( id );

        return field instanceof HTMLInputElement ? field : null;
    }

    /**
     * Devuelve el contenedor de la vista previa de un campo.
     *
     * @param {HTMLInputElement} field Campo oculto.
     * @return {HTMLElement|null}
     */
    function previewFor( field ) {
        return document.querySelector(
            '[data-atareao-preview="' + field.id + '"]'
        );
    }

    /**
     * Pinta el nombre del fichero elegido en la vista previa.
     *
     * @param {HTMLInputElement} field      Campo oculto.
     * @param {Object}           attachment Adjunto de la biblioteca.
     * @return {void}
     */
    function renderSelection( field, attachment ) {
        var preview = previewFor( field );
        if ( ! preview ) {
            return;
        }

        var name = attachment.filename || attachment.title || '';

        if ( 'image' === field.getAttribute( 'data-atareao-kind' ) ) {
            var url =
                attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;
            if ( url ) {
                var image = document.createElement( 'img' );
                image.src = url;
                image.alt = '';
                image.width = 150;
                image.height = 150;
                preview.innerHTML = '';
                preview.appendChild( image );
            }
        }

        var label = document.createElement( 'span' );
        label.className = 'atareao-media-name';
        label.textContent = name;
        preview.appendChild( label );
    }

    /**
     * Abre la biblioteca de medios para el campo de un botón.
     *
     * @param {HTMLElement} button Botón pulsado.
     * @return {void}
     */
    function openLibrary( button ) {
        if ( ! window.wp || ! window.wp.media ) {
            return;
        }

        var field = targetField( button );
        if ( ! field ) {
            return;
        }

        var kind = button.getAttribute( 'data-atareao-kind' ) || '';
        var options = {
            title: button.textContent,
            button: { text: 'Usar este archivo' },
            multiple: false
        };

        if ( 'image' === kind ) {
            options.library = { type: 'image' };
        }

        var frame = window.wp.media( options );

        frame.on( 'select', function () {
            var selection = frame.state().get( 'selection' );
            var attachment = selection && selection.first
                ? selection.first().toJSON()
                : null;
            if ( ! attachment || ! attachment.id ) {
                return;
            }

            field.value = String( attachment.id );
            field.setAttribute( 'data-atareao-kind', kind );
            renderSelection( field, attachment );
        } );

        frame.open();
    }

    /**
     * Vacía el campo y su vista previa.
     *
     * @param {HTMLElement} button Botón pulsado.
     * @return {void}
     */
    function clearField( button ) {
        var field = targetField( button );
        if ( ! field ) {
            return;
        }

        field.value = '';

        var preview = previewFor( field );
        if ( preview ) {
            preview.innerHTML = '';
        }
    }

    /**
     * Engancha los botones del metabox presentes en el documento.
     *
     * @return {void}
     */
    function bind() {
        var selectors = document.querySelectorAll( '.atareao-media-select' );
        var i;

        for ( i = 0; i < selectors.length; i++ ) {
            selectors[ i ].addEventListener( 'click', function ( event ) {
                event.preventDefault();
                openLibrary( event.currentTarget );
            } );
        }

        var removers = document.querySelectorAll( '.atareao-media-remove' );

        for ( i = 0; i < removers.length; i++ ) {
            removers[ i ].addEventListener( 'click', function ( event ) {
                event.preventDefault();
                clearField( event.currentTarget );
            } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', bind );
    } else {
        bind();
    }
} )();