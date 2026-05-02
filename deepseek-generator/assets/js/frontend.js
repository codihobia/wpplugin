( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var generators = document.querySelectorAll( '.dsg-generator' );
        generators.forEach( initGenerator );
        generators.forEach( initSmoke );
        initDanmaku();
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

    /* ── Simplex 2D / 3D Noise ── */

    function simplexNoise() {
        var grad3 = [
            [1,1,0],[-1,1,0],[1,-1,0],[-1,-1,0],
            [1,0,1],[-1,0,1],[1,0,-1],[-1,0,-1],
            [0,1,1],[0,-1,1],[0,1,-1],[0,-1,-1]
        ];
        var p = [151,160,137,91,90,15,131,13,201,95,96,53,194,233,7,225,140,36,103,30,69,142,8,99,37,240,21,10,23,190,6,148,247,120,234,75,0,26,197,62,94,252,219,203,117,35,11,32,57,177,33,88,237,149,56,87,174,20,125,136,171,168,68,175,74,165,71,134,139,48,27,166,77,146,158,231,83,111,229,122,60,211,133,230,220,105,92,41,55,46,245,40,244,102,143,54,65,25,63,161,1,216,80,73,209,76,132,187,208,89,18,169,200,196,135,130,116,188,159,86,164,100,109,198,173,186,3,64,52,217,226,250,124,123,5,202,38,147,118,126,255,82,85,212,207,206,59,227,47,16,58,17,182,189,28,42,223,183,170,213,119,248,152,2,44,154,163,70,221,153,101,155,167,43,172,9,129,22,39,253,19,98,108,110,79,113,224,232,178,185,112,104,218,246,97,228,251,34,242,193,238,210,144,12,191,179,162,241,81,51,145,235,249,14,239,107,49,192,214,31,181,199,106,157,184,84,204,176,115,121,50,45,127,4,150,254,138,236,205,93,222,114,67,29,24,72,243,141,128,195,78,66,215,61,156,180];
        var perm = new Array(512);
        var gradP = new Array(512);
        for (var i = 0; i < 512; i++) {
            perm[i] = p[i & 255];
            gradP[i] = grad3[perm[i] % 12];
        }

        function dot(g, x, y, z) {
            return g[0] * x + g[1] * y + g[2] * z;
        }

        return {
            noise3D: function(x, y, z) {
                var F3 = 1 / 3, G3 = 1 / 6;
                var s = (x + y + z) * F3;
                var i = Math.floor(x + s), j = Math.floor(y + s), k = Math.floor(z + s);
                var t = (i + j + k) * G3;
                var x0 = x - (i - t), y0 = y - (j - t), z0 = z - (k - t);

                var i1, j1, k1, i2, j2, k2;
                if (x0 >= y0) {
                    if (y0 >= z0) { i1=1;j1=0;k1=0;i2=1;j2=1;k2=0; }
                    else if (x0 >= z0) { i1=1;j1=0;k1=0;i2=1;j2=0;k2=1; }
                    else { i1=0;j1=0;k1=1;i2=1;j2=0;k2=1; }
                } else {
                    if (y0 < z0) { i1=0;j1=0;k1=1;i2=0;j2=1;k2=1; }
                    else if (x0 < z0) { i1=0;j1=1;k1=0;i2=0;j2=1;k2=1; }
                    else { i1=0;j1=1;k1=0;i2=1;j2=1;k2=0; }
                }

                var x1 = x0 - i1 + G3, y1 = y0 - j1 + G3, z1 = z0 - k1 + G3;
                var x2 = x0 - i2 + 2*G3, y2 = y0 - j2 + 2*G3, z2 = z0 - k2 + 2*G3;
                var x3 = x0 - 1 + 3*G3, y3 = y0 - 1 + 3*G3, z3 = z0 - 1 + 3*G3;

                i &= 255; j &= 255; k &= 255;
                var gi0 = gradP[i + perm[j + perm[k]]];
                var gi1 = gradP[i + i1 + perm[j + j1 + perm[k + k1]]];
                var gi2 = gradP[i + i2 + perm[j + j2 + perm[k + k2]]];
                var gi3 = gradP[i + 1 + perm[j + 1 + perm[k + 1]]];

                var t0 = 0.6 - x0*x0 - y0*y0 - z0*z0;
                var n0 = t0 < 0 ? 0 : (t0 *= t0, t0 * t0 * dot(gi0, x0, y0, z0));
                var t1 = 0.6 - x1*x1 - y1*y1 - z1*z1;
                var n1 = t1 < 0 ? 0 : (t1 *= t1, t1 * t1 * dot(gi1, x1, y1, z1));
                var t2 = 0.6 - x2*x2 - y2*y2 - z2*z2;
                var n2 = t2 < 0 ? 0 : (t2 *= t2, t2 * t2 * dot(gi2, x2, y2, z2));
                var t3 = 0.6 - x3*x3 - y3*y3 - z3*z3;
                var n3 = t3 < 0 ? 0 : (t3 *= t3, t3 * t3 * dot(gi3, x3, y3, z3));

                return 32 * (n0 + n1 + n2 + n3);
            },
            noise2D: function(x, y) {
                return this.noise3D(x, y, 0);
            }
        };
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

        var noise         = simplexNoise();
        var flowTime      = 0;
        var globalSwingPhase = 0;

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
                noiseSeedX: Math.random() * 1000,
                noiseSeedY: Math.random() * 1000,
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

            flowTime += dt * 0.0008 * speed;
            globalSwingPhase += dt * 0.001 * speed;

            var flowScaleX = 0.004;
            var flowScaleY = 0.006;
            var flowAmp    = 0.22;
            var sharedSway = Math.sin( globalSwingPhase ) * 0.6;

            for ( var i = particles.length - 1; i >= 0; i-- ) {
                var p = particles[ i ];
                p.life += dt;

                var nx = noise.noise3D( p.x * flowScaleX, p.y * flowScaleY + p.noiseSeedX, flowTime );
                var ny = noise.noise3D( p.x * flowScaleX + p.noiseSeedY, p.y * flowScaleY, flowTime );

                p.x += ( nx * flowAmp + sharedSway ) * dt * 0.05 * spreadFactor;
                p.y -= ( p.speed * 0.28 + ny * 0.05 ) * dt;

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

    /* ── Danmaku Background Text ── */

    function initDanmaku() {
        if ( ! dsgConfig || ! dsgConfig.danmaku || ! dsgConfig.danmaku.config || ! dsgConfig.danmaku.config.enabled ) {
            return;
        }

        var config    = dsgConfig.danmaku.config;
        var sentences = dsgConfig.danmaku.sentences;

        if ( ! sentences || sentences.length === 0 ) return;

        var activeDanmaku = [];
        var isPageVisible = true;
        var spawnTimer     = null;
        var tickId         = null;

        document.addEventListener( 'visibilitychange', function () {
            isPageVisible = ! document.hidden;
        } );

        function randomInt( min, max ) {
            return Math.floor( Math.random() * ( max - min + 1 ) ) + min;
        }

        function randomFloat( min, max ) {
            return Math.random() * ( max - min ) + min;
        }

        function pickRandom( arr ) {
            return arr[ Math.floor( Math.random() * arr.length ) ];
        }

        function isGradient( colorStr ) {
            var s = colorStr.trim();
            return /^(linear|radial|conic)-gradient|repeating-/.test( s );
        }

        function getBlockRects() {
            var blocks = document.querySelectorAll( '.dsg-generator' );
            var rects  = [];
            var pad    = 30;
            for ( var i = 0; i < blocks.length; i++ ) {
                var r = blocks[ i ].getBoundingClientRect();
                rects.push( {
                    left:   r.left - pad,
                    top:    r.top - pad,
                    right:  r.right + pad,
                    bottom: r.bottom + pad
                } );
            }
            return rects;
        }

        function calcMinDistance( text, fontSizeRem ) {
            var fontSizePx     = fontSizeRem * 16;
            var estimatedWidth = text.length * fontSizePx * 0.7;
            return Math.max( 60, estimatedWidth * 1.2 );
        }

        function findSafePosition( text, fontSizeRem ) {
            var vw    = window.innerWidth;
            var vh    = window.innerHeight;
            var rects = getBlockRects();
            var minD  = calcMinDistance( text, fontSizeRem );

            function insideBlock( x, y ) {
                for ( var i = 0; i < rects.length; i++ ) {
                    var b = rects[ i ];
                    if ( x >= b.left && x <= b.right && y >= b.top && y <= b.bottom ) {
                        return true;
                    }
                }
                return false;
            }

            function tooClose( x, y, threshold ) {
                for ( var j = 0; j < activeDanmaku.length; j++ ) {
                    var a = activeDanmaku[ j ];
                    var dx = x - a.x;
                    var dy = y - a.y;
                    if ( Math.sqrt( dx * dx + dy * dy ) < threshold ) {
                        return true;
                    }
                }
                return false;
            }

            var x, y;

            for ( var attempt = 0; attempt < 20; attempt++ ) {
                x = randomFloat( vw * 0.03, vw * 0.85 );
                y = randomFloat( vh * 0.05, vh * 0.85 );
                if ( ! insideBlock( x, y ) && ! tooClose( x, y, minD ) ) {
                    return { x: x, y: y };
                }
            }

            var relaxedD = Math.max( 40, minD * 0.5 );
            for ( attempt = 0; attempt < 10; attempt++ ) {
                x = randomFloat( vw * 0.03, vw * 0.85 );
                y = randomFloat( vh * 0.05, vh * 0.85 );
                if ( ! insideBlock( x, y ) && ! tooClose( x, y, relaxedD ) ) {
                    return { x: x, y: y };
                }
            }

            return {
                x: randomFloat( vw * 0.03, vw * 0.85 ),
                y: randomFloat( vh * 0.05, vh * 0.85 )
            };
        }

        function spawn() {
            if ( ! isPageVisible ) return;
            if ( activeDanmaku.length >= config.max_count ) return;

            var text = pickRandom( sentences );
            var span = document.createElement( 'span' );
            span.className = 'dsg-danmaku-text';
            span.textContent = text;

            var size      = randomFloat( config.min_size, config.max_size );
            size           = Math.round( size * 100 ) / 100;
            var opacity   = randomFloat( config.opacity_min, config.opacity_max );
            opacity        = Math.round( opacity * 1000 ) / 1000;
            var color     = pickRandom( config.colors );
            var duration  = randomInt( config.duration_min, config.duration_max );
            var fadeIn    = config.fade_in;
            var fadeOut   = config.fade_out;

            var pos = findSafePosition( text, size );
            var x   = pos.x;
            var y   = pos.y;

            var driftRange = config.drift_range;
            var startX     = x;
            var startY     = y;
            var driftX     = randomFloat( -driftRange, driftRange );
            var driftY     = randomFloat( -driftRange * 0.6, driftRange * 0.3 );
            var driftPeriod = duration;

            span.style.fontSize = size + 'rem';
            span.style.left     = x + 'px';
            span.style.top      = y + 'px';
            span.style.transitionDuration = fadeIn + 'ms';

            if ( isGradient( color ) ) {
                span.className += ' dsg-danmaku-gradient';
                span.style.backgroundImage = color;
            } else {
                span.style.color = color;
            }

            document.body.appendChild( span );

            var state = {
                el:          span,
                x:           x,
                y:           y,
                startX:      startX,
                startY:      startY,
                driftX:      driftX,
                driftY:      driftY,
                driftPeriod: driftPeriod,
                opacity:     opacity,
                duration:    duration,
                fadeIn:      fadeIn,
                fadeOut:     fadeOut,
                startTime:   performance.now(),
                phase:       'fadeIn'
            };

            activeDanmaku.push( state );

            requestAnimationFrame( function () {
                span.style.opacity = opacity;
            } );

            setTimeout( function () {
                state.phase = 'drift';
            }, fadeIn );

            setTimeout( function () {
                state.phase = 'fadeOut';
                span.style.transitionDuration = fadeOut + 'ms';
                span.style.opacity = '0';
            }, fadeIn + duration );

            setTimeout( function () {
                if ( span.parentNode ) span.parentNode.removeChild( span );
                var idx = activeDanmaku.indexOf( state );
                if ( idx > -1 ) activeDanmaku.splice( idx, 1 );
            }, fadeIn + duration + fadeOut + 100 );

            if ( ! tickId ) {
                tickId = requestAnimationFrame( tick );
            }
        }

        function tick( timestamp ) {
            var stillAlive = false;

            for ( var i = 0; i < activeDanmaku.length; i++ ) {
                var s = activeDanmaku[ i ];
                if ( s.phase === 'fadeOut' ) {
                    stillAlive = true;
                    continue;
                }
                stillAlive = true;

                var elapsed = timestamp - s.startTime - s.fadeIn;
                if ( elapsed < 0 ) continue;

                var t = Math.min( elapsed / s.driftPeriod, 1 );
                var ease = t < 0.5 ? 2 * t * t : -1 + ( 4 - 2 * t ) * t;

                var dx = s.driftX * ease;
                var dy = s.driftY * ease;

                s.x = s.startX + dx;
                s.y = s.startY + dy;

                s.el.style.left = s.x + 'px';
                s.el.style.top  = s.y + 'px';
            }

            if ( stillAlive ) {
                tickId = requestAnimationFrame( tick );
            } else {
                tickId = null;
            }
        }

        spawnTimer = setInterval( spawn, config.interval );
        spawn();
    }

} )();
