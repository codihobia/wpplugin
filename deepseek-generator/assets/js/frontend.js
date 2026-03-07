( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var generators = document.querySelectorAll( '.dsg-generator' );
        generators.forEach( initGenerator );
    } );

    function initGenerator( container ) {
        var templateId   = container.dataset.templateId;
        var input        = container.querySelector( '.dsg-input' );
        var btnGenerate  = container.querySelector( '.dsg-btn-generate' );
        var btnText      = container.querySelector( '.dsg-btn-text' );
        var spinner      = container.querySelector( '.dsg-spinner' );
        var outputArea   = container.querySelector( '.dsg-output-area' );
        var outputContent = container.querySelector( '.dsg-output-content' );
        var btnCopy      = container.querySelector( '.dsg-btn-copy' );
        var btnRegenerate = container.querySelector( '.dsg-btn-regenerate' );
        var exampleBlock = container.querySelector( '.dsg-example' );

        var rawContent   = '';
        var isGenerating = false;

        btnGenerate.addEventListener( 'click', function () {
            if ( isGenerating ) return;
            doGenerate();
        } );

        input.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' && ( e.ctrlKey || e.metaKey ) ) {
                e.preventDefault();
                if ( ! isGenerating ) doGenerate();
            }
        } );

        if ( btnCopy ) {
            btnCopy.addEventListener( 'click', function () {
                if ( ! rawContent ) return;
                navigator.clipboard.writeText( rawContent ).then( function () {
                    var span = btnCopy.querySelector( 'span' );
                    var original = span.textContent;
                    span.textContent = dsgConfig.i18n.copied;
                    setTimeout( function () { span.textContent = original; }, 1500 );
                } );
            } );
        }

        if ( btnRegenerate ) {
            btnRegenerate.addEventListener( 'click', function () {
                if ( isGenerating ) return;
                doGenerate();
            } );
        }

        function setLoading( loading ) {
            isGenerating = loading;
            btnGenerate.disabled = loading;
            spinner.style.display = loading ? 'inline-block' : 'none';
            btnText.textContent = loading ? dsgConfig.i18n.generating : dsgConfig.i18n.generate;
            if ( loading ) {
                btnGenerate.classList.add( 'dsg-loading' );
            } else {
                btnGenerate.classList.remove( 'dsg-loading' );
            }
        }

        function doGenerate() {
            var userInput = input.value.trim();
            if ( ! userInput ) {
                shakeElement( input );
                return;
            }

            setLoading( true );
            rawContent = '';
            outputContent.innerHTML = '';
            outputArea.style.display = 'block';

            if ( exampleBlock ) {
                exampleBlock.style.display = 'none';
            }

            var formData = new FormData();
            formData.append( 'action', 'deepseek_generate' );
            formData.append( 'nonce', dsgConfig.nonce );
            formData.append( 'template_id', templateId );
            formData.append( 'user_input', userInput );
            formData.append( 'stream', '1' );

            fetch( dsgConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            } )
                .then( function ( response ) {
                    if ( ! response.ok ) {
                        return response.json().then( function ( d ) {
                            throw new Error( d.data || dsgConfig.i18n.error );
                        } );
                    }

                    var contentType = response.headers.get( 'content-type' ) || '';
                    if ( contentType.indexOf( 'text/event-stream' ) !== -1 ) {
                        return readStream( response );
                    }

                    return response.json().then( function ( d ) {
                        if ( d.success ) {
                            rawContent = d.data.content;
                            renderMarkdown( outputContent, rawContent );
                        } else {
                            throw new Error( d.data || dsgConfig.i18n.error );
                        }
                    } );
                } )
                .catch( function ( err ) {
                    outputContent.innerHTML = '<div class="dsg-error-msg">' + escapeHtml( err.message ) + '</div>';
                } )
                .finally( function () {
                    setLoading( false );
                } );
        }

        function readStream( response ) {
            var reader  = response.body.getReader();
            var decoder = new TextDecoder( 'utf-8' );
            var buffer  = '';

            function process( result ) {
                if ( result.done ) {
                    renderMarkdown( outputContent, rawContent );
                    return;
                }

                buffer += decoder.decode( result.value, { stream: true } );
                var lines = buffer.split( '\n' );
                buffer = lines.pop();

                for ( var i = 0; i < lines.length; i++ ) {
                    var line = lines[ i ].trim();
                    if ( ! line.startsWith( 'data: ' ) ) continue;

                    var payload = line.substring( 6 );
                    if ( payload === '[DONE]' ) {
                        renderMarkdown( outputContent, rawContent );
                        return;
                    }

                    try {
                        var json = JSON.parse( payload );
                        if ( json.error ) {
                            outputContent.innerHTML = '<div class="dsg-error-msg">' + escapeHtml( json.error ) + '</div>';
                            return;
                        }
                        var delta = json.choices && json.choices[ 0 ] && json.choices[ 0 ].delta;
                        if ( delta && delta.content ) {
                            rawContent += delta.content;
                            outputContent.textContent = rawContent;
                            outputArea.scrollTop = outputArea.scrollHeight;
                        }
                    } catch ( e ) {
                        // skip malformed chunks
                    }
                }

                return reader.read().then( process );
            }

            return reader.read().then( process );
        }
    }

    function renderMarkdown( el, text ) {
        if ( window.marked && text ) {
            el.innerHTML = window.marked.parse( text );
        }
    }

    function escapeHtml( str ) {
        var div = document.createElement( 'div' );
        div.textContent = str;
        return div.innerHTML;
    }

    function shakeElement( el ) {
        el.classList.add( 'dsg-shake' );
        setTimeout( function () { el.classList.remove( 'dsg-shake' ); }, 500 );
    }

} )();
