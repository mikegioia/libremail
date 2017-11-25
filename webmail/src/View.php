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
     * Add data to the internal variables. This is chainable.
     * @param array $data
     * @return self
     */
    public function setData( $data )
    {
        $this->data = array_merge( $this->data, $data );

        return $this;
    }

    /**
     * Render the requested view. This will clear the data
     * array unless told not to.
     * @param string $view
     * @param array $data Optional, additional data to add
     * @param bool $clearData Defaults to true
     * @throws Exception
     * @return string
     */
    public function render( $view, $data = [], $preserveData = FALSE )
    {
        $viewPath = VIEWDIR . DIR . $view . VIEWEXT;

        if ( ! file_exists( $viewPath ) ) {
            throw new Exception( 'View not found! '. $viewPath );
        }

        if ( $data ) {
            $this->data = array_merge( $this->data, $data );
        }

        // Add helper functions to the data array
        $this->addHelpers();

        ob_start();
        extract( $this->data );

        include $viewPath;

        if ( ! $preserveData ) {
            $this->data = [];
        }

        return ob_get_clean();
    }

    private function addHelpers()
    {
        $view = new static;

        $this->data[ 'render' ] = function ( ...$params ) use ( $view ) {
            return call_user_func_array( [ $view, 'render' ], $params );
        };
    }
}