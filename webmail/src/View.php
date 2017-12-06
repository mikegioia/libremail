<?php

/**
 * Simple view rendering class. Uses output buffer and native
 * PHP templates.
 */

namespace App;

use Exception;

class View
{
    private $data = [];

    /**
     * Add data to the internal variables. This is chainable,
     * and it will permanently store this data across renders.
     * @param array $data
     * @return self
     */
    public function setData( array $data )
    {
        $this->data = array_merge( $this->data, $data );

        return $this;
    }

    /**
     * Render the requested view via echo. This will clear the data
     * array unless told not to.
     * @param string $view
     * @param array $data View data
     * @param bool $return Whether to return the string
     * @throws Exception
     */
    public function render( $view, array $data = [], $return = FALSE )
    {
        $viewPath = VIEWDIR . DIR . $view . VIEWEXT;

        if ( ! file_exists( $viewPath ) ) {
            throw new Exception( 'View not found! '. $viewPath );
        }

        ob_start();
        extract( array_merge( $this->data, $data ) );

        include $viewPath;

        if ( $return ) {
            return ob_get_clean();
        }
        else {
            echo ob_get_clean();
        }
    }

    /**
     * Prepares a data URI attribute for an element. Escapes the
     * HTML to comply with a data:TYPE attribute.
     * @param string $view
     * @throws Exception
     * @return string
     */
    public function dataUri( $view, array $data = [] )
    {
        $html = $this->render( $view, $data, TRUE );
        $search = [ '%', '&', '#', '"', "'" ];
        $replace = [ '%25', '%26', '%23', '%22', '%27' ];
        $html = preg_replace( '/\s+/', ' ', $html );

        return str_replace( $search, $replace, $html );
    }
}