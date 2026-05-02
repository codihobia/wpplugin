( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var generators = document.querySelectorAll( '.dsg-generator' );
        generators.forEach( initGenerator );
        generators.forEach( initSmoke );
    } );

    function initGenerator( container ) {
        var templateId    = container.dataset.templateId;
        var saveable      = container.dataset.saveable === '1';
        var showHistory   = container.dataset.showHistory === '1';
        var input         = container.querySelector( '.dsg-input' );
        var btnGenerate   = container.querySelector( '.dsg-btn-generate' );
        var btnText       = container.querySelector( '.dsg-btn-text' );
        var spinner       = container.querySelector( '.dsg-spinner' );
        var outputArea    = container.querySelector( '.dsg-output-area' );
        var outputContent = container.querySelector( '.dsg-output-content' );
        var btnCopy       = container.querySelector( '.dsg-btn-copy' );
        var btnRegenerate = container.querySelector( '.dsg-btn-regenerate' );
        var btnSave       = container.querySelector( '.dsg-btn-save' );
        var exampleBlock  = container.querySelector( '.dsg-example' );
        var historyList   = container.querySelector( '.dsg-history-list' );
        var historyMore   = container.querySelector( '.dsg-history-more' );
        var btnLoadMore   = container.querySelector( '.dsg-btn-load-more' );

        var rawContent    = '';
        var isGenerating  = false;
        var historyPage   = 1;
        var lastUserInput = '';

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
                    flashButtonText( btnCopy, dsgConfig.i18n.copied );
                } );
            } );
        }

        if ( btnRegenerate ) {
            btnRegenerate.addEventListener( 'click', function () {
                if ( isGenerating ) return;
                doGenerate();
            } );
        }

        if ( btnSave ) {
            btnSave.addEventListener( 'click', function () {
                if ( ! rawContent ) return;
                doSave();
            } );
        }

        if ( btnLoadMore ) {
            btnLoadMore.addEventListener( 'click', function () {
                loadHistory( false );
            } );
        }

        if ( showHistory && historyList ) {
            loadHistory( true );
        }

        function flashButtonText( btn, text ) {
            var span = btn.querySelector( 'span' );
            var original = span.textContent;
            span.textContent = text;
            setTimeout( function () { span.textContent = original; }, 1500 );
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

            lastUserInput = userInput;
            setLoading( true );
            rawContent = '';
            outputContent.innerHTML = '';
            outputArea.style.display = 'block';

            if ( btnSave ) {
                btnSave.style.display = 'none';
            }

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
                            onGenerateComplete();
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

        function onGenerateComplete() {
            renderMarkdown( outputContent, rawContent );
            if ( btnSave && saveable && rawContent ) {
                btnSave.style.display = '';
            }
        }

        function readStream( response ) {
            var reader  = response.body.getReader();
            var decoder = new TextDecoder( 'utf-8' );
            var buffer  = '';

            function process( result ) {
                if ( result.done ) {
                    onGenerateComplete();
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
                        onGenerateComplete();
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

        function doSave() {
            if ( ! rawContent || ! btnSave ) return;

            var span = btnSave.querySelector( 'span' );
            span.textContent = dsgConfig.i18n.saving;
            btnSave.disabled = true;

            var formData = new FormData();
            formData.append( 'action', 'dsg_save_output' );
            formData.append( 'nonce', dsgConfig.nonce );
            formData.append( 'template_id', templateId );
            formData.append( 'user_input', lastUserInput );
            formData.append( 'output', rawContent );

            fetch( dsgConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            } )
                .then( function ( response ) { return response.json(); } )
                .then( function ( data ) {
                    if ( data.success ) {
                        span.textContent = dsgConfig.i18n.saved;
                        btnSave.classList.add( 'dsg-saved' );
                        setTimeout( function () {
                            btnSave.style.display = 'none';
                            btnSave.classList.remove( 'dsg-saved' );
                            span.textContent = dsgConfig.i18n.save;
                            btnSave.disabled = false;
                        }, 2000 );

                        if ( showHistory && historyList ) {
                            prependHistoryItem( {
                                output: rawContent,
                                user_input: lastUserInput,
                                user_name: data.data.user_name,
                                created_at: data.data.created_at,
                            } );
                        }
                    } else {
                        span.textContent = dsgConfig.i18n.save;
                        btnSave.disabled = false;
                        alert( data.data || dsgConfig.i18n.save_fail );
                    }
                } )
                .catch( function () {
                    span.textContent = dsgConfig.i18n.save;
                    btnSave.disabled = false;
                } );
        }

        function loadHistory( reset ) {
            if ( reset ) {
                historyPage = 1;
                if ( historyList ) historyList.innerHTML = '';
            } else {
                historyPage++;
            }

            var formData = new FormData();
            formData.append( 'action', 'dsg_load_outputs' );
            formData.append( 'nonce', dsgConfig.nonce );
            formData.append( 'template_id', templateId );
            formData.append( 'page', String( historyPage ) );

            fetch( dsgConfig.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( data ) {
                    if ( ! data.success ) return;

                    var items = data.data.items;
                    var pages = data.data.pages;

                    if ( items.length === 0 && historyPage === 1 ) {
                        historyList.innerHTML = '<div class="dsg-history-empty">' + escapeHtml( dsgConfig.i18n.no_history ) + '</div>';
                    }

                    items.forEach( function ( item ) {
                        appendHistoryItem( item );
                    } );

                    if ( historyMore ) {
                        historyMore.style.display = historyPage < pages ? '' : 'none';
                    }
                } );
        }

        function buildHistoryCard( item ) {
            var card = document.createElement( 'div' );
            card.className = 'dsg-history-card';

            var meta = document.createElement( 'div' );
            meta.className = 'dsg-history-meta';
            meta.innerHTML = '<span class="dsg-history-author">' + escapeHtml( item.user_name ) + '</span>' +
                '<span class="dsg-history-date">' + escapeHtml( item.created_at ) + '</span>';

            var prompt = document.createElement( 'div' );
            prompt.className = 'dsg-history-prompt';
            prompt.textContent = item.user_input;

            var body = document.createElement( 'div' );
            body.className = 'dsg-history-body';
            renderMarkdown( body, item.output );

            card.appendChild( meta );
            card.appendChild( prompt );
            card.appendChild( body );
            return card;
        }

        function appendHistoryItem( item ) {
            if ( ! historyList ) return;
            var empty = historyList.querySelector( '.dsg-history-empty' );
            if ( empty ) empty.remove();
            historyList.appendChild( buildHistoryCard( item ) );
        }

        function prependHistoryItem( item ) {
            if ( ! historyList ) return;
            var empty = historyList.querySelector( '.dsg-history-empty' );
            if ( empty ) empty.remove();
            var card = buildHistoryCard( item );
            card.classList.add( 'dsg-history-new' );
            historyList.insertBefore( card, historyList.firstChild );
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

    /* ── Smoke Particle System ── */

    function initSmoke( container ) {
        var canvas = container.querySelector( '.dsg-smoke-canvas' );
        if ( ! canvas ) return;

        var config = ( dsgConfig && dsgConfig.smoke ) ? dsgConfig.smoke : {};
        if ( ! config.enabled ) return;

        var ctx         = canvas.getContext( '2d' );
        var particles   = [];
        var animId      = null;
        var observer    = null;
        var visObserver = null;
        var isVisible   = true;
        var isPageVisible = true;
        var lastTime    = 0;
        var frameSkipThreshold = 1000 / 25;
        var dpr         = Math.min( window.devicePixelRatio || 1, 2 );

        var particleCount = config.particles || 80;
        var speed         = config.speed || 0.6;
        var maxOpacity    = config.opacity || 0.3;
        var smokeColor    = config.color || '#4f46e5';
        var spreadFactor  = config.spread || 1.0;
        var breathe       = config.breathe || 0.5;
        var breathePhase  = 0;

        var rgb = hexToRgb( smokeColor );
        var r = rgb.r;
        var g = rgb.g;
        var b = rgb.b;

        function resize() {
            var rect = container.getBoundingClientRect();
            var w = rect.width;
            var h = rect.height;
            if ( w <= 0 || h <= 0 ) return;
            canvas.width  = w * dpr;
            canvas.height = h * dpr;
            canvas.style.width  = w + 'px';
            canvas.style.height = h + 'px';
            ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
            spawnParticles( w, h );
        }

        function spawnParticles( w, h ) {
            var targetCount = particleCount;
            while ( particles.length < targetCount ) {
                particles.push( createParticle( w, h, true ) );
            }
            while ( particles.length > targetCount ) {
                particles.pop();
            }
        }

        function createParticle( w, h, initial ) {
            var cx = w * 0.5;
            return {
                x: cx + ( Math.random() - 0.5 ) * w * spreadFactor,
                y: initial ? Math.random() * h : h + Math.random() * 20,
                radius: 15 + Math.random() * 55,
                speed: ( 0.3 + Math.random() * 1.2 ) * speed,
                wobbleAmp: 0.2 + Math.random() * 1.4,
                wobbleFreq: 0.005 + Math.random() * 0.025,
                wobbleOffset: Math.random() * Math.PI * 2,
                opacity: 0,
                targetOpacity: 0.15 + Math.random() * 0.6,
                life: 0,
                maxLife: 3000 + Math.random() * 5000,
                fadeInDuration: 200 + Math.random() * 400,
            };
        }

        function update( dt ) {
            var w = container.getBoundingClientRect().width;
            var h = container.getBoundingClientRect().height;
            if ( w <= 0 || h <= 0 ) return;

            breathePhase += dt * 0.001 * speed;
            var breathFactor = 1.0 + Math.sin( breathePhase ) * breathe;

            for ( var i = particles.length - 1; i >= 0; i-- ) {
                var p = particles[ i ];
                p.life += dt;

                p.y -= p.speed * dt * 0.28;
                p.x += Math.sin( p.life * p.wobbleFreq + p.wobbleOffset ) * p.wobbleAmp * dt * 0.03 * spreadFactor;

                if ( p.life < p.fadeInDuration ) {
                    p.opacity = p.targetOpacity * ( p.life / p.fadeInDuration );
                } else if ( p.life > p.maxLife - 400 ) {
                    var fadeOut = ( p.maxLife - p.life ) / 400;
                    p.opacity = p.targetOpacity * Math.max( 0, fadeOut );
                } else {
                    p.opacity = p.targetOpacity;
                }

                p.radius += dt * 0.008 * speed;

                if ( p.y + p.radius < -100 || p.life > p.maxLife || p.opacity <= 0.005 ) {
                    particles[ i ] = createParticle( w, h, false );
                }
            }
        }

        function draw() {
            var w = container.getBoundingClientRect().width;
            var h = container.getBoundingClientRect().height;
            if ( w <= 0 || h <= 0 ) return;

            ctx.clearRect( 0, 0, w, h );

            for ( var i = 0; i < particles.length; i++ ) {
                var p = particles[ i ];
                var opacity = p.opacity * maxOpacity;
                if ( opacity < 0.003 ) continue;

                var cx = p.x;
                var cy = p.y;
                var rad = p.radius;

                var grad = ctx.createRadialGradient( cx, cy, 0, cx, cy, rad );
                grad.addColorStop( 0, 'rgba(' + r + ',' + g + ',' + b + ',' + ( opacity * 1.2 ).toFixed( 3 ) + ')' );
                grad.addColorStop( 0.3, 'rgba(' + r + ',' + g + ',' + b + ',' + ( opacity * 0.7 ).toFixed( 3 ) + ')' );
                grad.addColorStop( 0.7, 'rgba(' + r + ',' + g + ',' + b + ',' + ( opacity * 0.15 ).toFixed( 3 ) + ')' );
                grad.addColorStop( 1, 'rgba(' + r + ',' + g + ',' + b + ',0)' );

                ctx.beginPath();
                ctx.arc( cx, cy, rad, 0, Math.PI * 2 );
                ctx.fillStyle = grad;
                ctx.fill();
            }
        }

        function loop( timestamp ) {
            if ( ! isVisible || ! isPageVisible ) {
                animId = requestAnimationFrame( loop );
                return;
            }

            if ( lastTime === 0 ) lastTime = timestamp;
            var dt = timestamp - lastTime;
            lastTime = timestamp;

            if ( dt > frameSkipThreshold ) {
                dt = frameSkipThreshold;
            }

            update( dt );
            draw();
            animId = requestAnimationFrame( loop );
        }

        function start() {
            resize();
            animId = requestAnimationFrame( loop );
        }

        function stop() {
            if ( animId ) {
                cancelAnimationFrame( animId );
                animId = null;
            }
            lastTime = 0;
        }

        // ResizeObserver for container size changes
        observer = new ResizeObserver( function () {
            var w = container.getBoundingClientRect().width;
            var h = container.getBoundingClientRect().height;
            if ( w !== parseFloat( canvas.style.width ) || h !== parseFloat( canvas.style.height ) ) {
                resize();
            }
        } );
        observer.observe( container );

        // IntersectionObserver: pause when off screen
        visObserver = new IntersectionObserver( function ( entries ) {
            isVisible = entries[ 0 ].isIntersecting;
        }, { threshold: 0 } );
        visObserver.observe( container );

        // visibilitychange: pause when tab is hidden
        document.addEventListener( 'visibilitychange', function () {
            isPageVisible = ! document.hidden;
        } );

        start();
    }

    function hexToRgb( hex ) {
        hex = hex.replace( '#', '' );
        if ( hex.length === 3 ) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var num = parseInt( hex, 16 );
        return { r: ( num >> 16 ) & 255, g: ( num >> 8 ) & 255, b: num & 255 };
    }

} )();
