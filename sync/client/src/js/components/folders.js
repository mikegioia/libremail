/**
 * Folders Component
 */
LibreMail.Components.Folders = (function ( Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.folders';
    // Flag if the system is "auto-scrolling"
    var syncActive;
    // Flag to reset scroll
    var hasScrolled;
    // Used for comparison during render
    var folderList = [];
    // Used for throttling full re-draws. While a sync is in
    // progress we'll get a stream of update messages. Inter-
    // mixed in those will be messages without an active folder.
    // The absence of the active folder will cause the update
    // to re-draw every folder and jitter the screen. We can
    // prevent this with a throttle timer.
    var redrawTimer;
    // Used for crawling stale folders that may still have an
    // incomplete flag on them.
    var spiderTimer;
    var spiderStore = {};
    // This is not used, see crawlFolders
    var spiderWaitMs = 0;
    var activeFlag = false;
    var spiderDelayMs = 5000;
    var spiderTimeoutMs = 2000;
    var redrawTimeoutMs = 10000;
    // DOM template nodes
    var $folder = document.getElementById( 'folder' );
    var $folders = document.getElementById( 'folders' );
    // Templates
    var tpl = {
        folder: $folder.innerHTML,
        folders: $folders.innerHTML
    };

    // Parse the templates
    Mustache.parse( tpl.folder );
    Mustache.parse( tpl.folders );

    function render ( d ) {
        var i;
        var folders;
        var folderNames;

        if ( ! d.account
            || ! d.accounts
            || ! Object.keys( d.accounts ).length )
        {
            return;
        }

        folderNames = Object.keys( d.accounts[ d.account ] );
        folders = formatFolders( d.accounts[ d.account ], d.active );

        // If we already rendered the folders, just perform
        // an update on the folder meta.
        if ( folderList.length
            && arraysEqual( folderList, folderNames ) )
        {
            update( folders, d.active );
        }
        else {
            draw( folders );
        }

        folderList = folderNames;
        startSpiderCrawl( spiderDelayMs );

        if ( d.asleep || ( ! d.active && ! activeFlag ) ) {
            if ( hasScrolled === true && syncActive === true ) {
                window.scrollTo( 0, 0 );
                syncActive = false;
            }

            return;
        }

        for ( i in folders ) {
            if ( folders[ i ].active ) {
                syncActive = true;
                scrollTo( folders[ i ].id );
                break;
            }
        }
    }

    function draw ( folders ) {
        $root.innerHTML = Mustache.render(
            tpl.folders, {
                folders: folders
            }, {
                folder: tpl.folder
            });
    }

    function update( folders, active ) {
        var i;
        var node;
        var activeNode;
        var activeNodes;

        // If there's an active folder, just update the active one
        if ( active ) {
            extendRedrawTimer();
            activeNodes = document.querySelectorAll( '.folder.active' );

            for ( i = 0; activeNode = activeNodes[ i ]; i++ ) {
                activeNode.className = activeNode
                    .className
                    .replace( "active", "" );
            }
        }

        for ( i in folders ) {
            node = document.getElementById( folders[ i ].id );

            if ( ! node ) {
                continue;
            }

            node.innerHTML = Mustache.render( tpl.folder, folders[ i ] );

            if ( ( ! active && ! activeFlag )
                || ( active && folders[ i ].path == active ) )
            {
                updateFolderClasses( node, folders[ i ] );
            }

            node = null;
        }
    }

    function updateFolderClasses ( node, folder ) {
        var classes = [ "folder" ];

        if ( folder.active ) {
            classes.push( "active" );
        }

        if ( folder.incomplete ) {
            classes.push( "incomplete" );
        }

        node.className = classes.join( " " );
        spiderStore[ folder.id ] = (new Date).getTime();
    }

    function cleanupFolderClasses ( node ) {
        var count;
        var synced;
        var classes;

        if ( ! node
            || ! node.className
            || node.className.indexOf( "active" ) !== -1 )
        {
            return;
        }

        count = node.querySelector( 'input.count' ).value;
        synced = node.querySelector( 'input.synced' ).value;

        if ( synced >= count ) {
            node.className = node.className.replace( "incomplete", "" );
        }
    }

    function tearDown () {
        folderList = [];
        syncActive = false;
        activeFlag = false;
        hasScrolled = false;
    }

    /**
     * Reads in a collection of accounts with folder metadata
     * and prepares it into a format for Mustache.
     * @param Object accounts
     * @param String active Active folder being synced
     * @return Array
     */
    function formatFolders ( folders, active ) {
        var i;
        var formatted = [];

        for ( i in folders ) {
            formatted.push({
                path: i,
                active: ( active === i ),
                count: folders[ i ].count,
                name: i.split( '/' ).pop(),
                synced: folders[ i ].synced,
                percent: folders[ i ].percent,
                id: 'folder-' + i.split( '/' ).join( '-' ),
                incomplete: folders[ i ].synced < folders[ i ].count,
                crumbs: function () {
                    var crumbs = this.path.split( '/' ).slice( 0, -1 );
                    return ( crumbs.length > 0 )
                        ? crumbs.join( '&nbsp;&rsaquo;&nbsp;' )
                        : '&nbsp;';
                }
            });
        }

        return formatted;
    }

    function yPosition ( node ) {
        var elt = node;
        var y = elt.offsetTop;

        while ( elt.offsetParent && elt.offsetParent != document.body ) {
            elt = elt.offsetParent;
            y += elt.offsetTop;
        }

        return y;
    }

    function scrollTo ( id ) {
        var yPos;
        var node = document.getElementById( id );

        if ( ! node ) {
            return;
        }

        yPos = yPosition( node );

        // If the element is fully visible, then don't scroll
        if ( yPos + node.clientHeight < window.innerHeight + window.scrollY
            && yPos > window.scrollY )
        {
            return;
        }

        window.scrollTo( 0, node.offsetTop );
        hasScrolled = true;
    }

    function arraysEqual ( a, b ) {
        var i;
        if ( a === b ) return true;
        if ( a == null || b == null ) return false;
        if ( a.length != b.length ) return false;

        a.sort();
        b.sort();

        for ( i = 0; i < a.length; i++ ) {
            if ( a[ i ] !== b[ i ] ) return false;
        }

        return true;
    }

    function extendRedrawTimer () {
        activeFlag = true;
        clearTimeout( redrawTimer );
        redrawTimer = setTimeout( function () {
            activeFlag = false;
        }, redrawTimeoutMs );
    }

    /**
     * Crawls the folders on a timer, looking for any that
     * should have their classname cleaned up.
     */
    function startSpiderCrawl ( timeout ) {
        clearTimeout( spiderTimer );
        spiderTimer = setTimeout( crawlFolders, spiderDelayMs );
    }

    function crawlFolders () {
        var i;
        var folders;
        var time = (new Date).getTime();

        if ( ! activeFlag ) {
            startSpiderCrawl( spiderTimeoutMs );
            return;
        }

        folders = document.querySelectorAll( '.folder.incomplete:not(.active)' );

        for ( i in folders ) {
            // If it's been active within a wait period, ignore it
            if ( spiderWaitMs
                && spiderStore[ folders[ i ].id ]
                && time - spiderStore[ folders[ i ].id ] < spiderWaitMs )
            {
                continue;
            }

            cleanupFolderClasses( folders[ i ] );
        }

        startSpiderCrawl( spiderTimeoutMs );
    }

    return {
        render: render,
        tearDown: tearDown
    };
}}( Mustache ));