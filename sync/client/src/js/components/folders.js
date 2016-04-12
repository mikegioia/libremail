/**
 * Folders Component
 */
LibreMail.Components.Folders = (function ( Mustache ) {
'use strict';
// Returns a new instance
return function ( $root ) {
    // Event namespace
    var namespace = '.folders';
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
    var activeFlag = false;
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
        var folderNames = Object.keys( d.accounts[ d.account ] );
        var folders = formatFolders( d.accounts[ d.account ], d.active );

        // If we already rendered the folders, just perform
        // an update on the folder meta.
        if ( arraysEqual( folderList, folderNames ) ) {
            update( folders, d.active );
        }
        else {
            draw( folders );
        }

        folderList = folderNames;

        if ( ! d.active ) {
            if ( hasScrolled === true ) {
                window.scrollTo( 0, 0 );
            }
            return;
        }

        for ( i in folders ) {
            if ( folders[ i ].active ) {
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
                activeNode.className = "folder";
            }
        }

        for ( i in folders ) {
            if ( ( ! active && ! activeFlag ) || folders[ i ].active ) {
                node = document.getElementById( folders[ i ].id );
                node.innerHTML = Mustache.render( tpl.folder, folders[ i ] );
                node.className = ( folders[ i ].active )
                    ? "folder active"
                    : "folder";
                node = null;
            }
        }
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
        var node = document.getElementById( id );
        var yPos = yPosition( node );

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
        setTimeout( function () {
            activeFlag = false;
        }, redrawTimeoutMs );
    }

    return {
        render: render
    };
}}( Mustache ));