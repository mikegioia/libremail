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
     * @throws Exception
     */
    public function render( $view, array $data = [] )
    {
        $viewPath = VIEWDIR . DIR . $view . VIEWEXT;

        if ( ! file_exists( $viewPath ) ) {
            throw new Exception( 'View not found! '. $viewPath );
        }

        ob_start();
        extract( array_merge( $this->data, $data ) );

        include $viewPath;

        echo ob_get_clean();
    }
}