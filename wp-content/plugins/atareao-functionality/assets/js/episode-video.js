/*
 * Fachada del vídeo del episodio.
 *
 * Mejora progresiva: sin JavaScript, `.episode-video__link` navega a la URL
 * canónica del vídeo. Con JavaScript, el clic se intercepta y el enlace se
 * sustituye por un <iframe> de youtube-nocookie.com (privacidad mejorada) que
 * se carga solo al pulsar. JS vanilla, sin jQuery ni dependencias.
 */
( function () {
    'use strict';

    /**
     * Sustituye el enlace de una fachada por el <iframe> del reproductor.
     *
     * @param {Element}      figure Figura `.episode-video` contenedora.
     * @param {HTMLElement}  link   Enlace `.episode-video__link` pulsado.
     * @return {void}
     */
    function loadVideo( figure, link ) {
        var id = ( figure && figure.getAttribute( 'data-video-id' ) ) ||
            ( link && link.getAttribute( 'data-video-id' ) );

        if ( ! id || ! link || ! link.parentNode ) {
            return;
        }

        var iframe = document.createElement( 'iframe' );
        iframe.className = 'episode-video__iframe';
        iframe.width = '560';
        iframe.height = '315';
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
        iframe.title = 'Vídeo del episodio';
        iframe.loading = 'lazy';
        iframe.setAttribute( 'allowfullscreen', '' );
        iframe.setAttribute(
            'allow',
            'accelerometer; autoplay; clipboard-write; encrypted-media; ' +
                'gyroscope; picture-in-picture'
        );

        link.parentNode.replaceChild( iframe, link );
    }

    /**
     * Engancha el clic de cada fachada presente en el documento.
     *
     * @return {void}
     */
    function bindPlayers() {
        var links = document.querySelectorAll( '.episode-video__link' );

        for ( var i = 0; i < links.length; i++ ) {
            links[ i ].addEventListener( 'click', function ( event ) {
                event.preventDefault();

                var link = event.currentTarget;
                var figure = link.closest
                    ? link.closest( '.episode-video' )
                    : null;

                loadVideo( figure || link.parentNode, link );
            } );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', bindPlayers );
    } else {
        bindPlayers();
    }
} )();
