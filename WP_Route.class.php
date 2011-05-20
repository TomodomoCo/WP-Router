<?php
/**
 * User: jbrinley
 * Date: 5/18/11
 * Time: 12:34 PM
 */
 
class WP_Route extends WP_Router_Utility {
	const QUERY_VAR = 'WP_Route';
	
	private $id = '';
	private $path = '';
	private $query_vars = array();
	private $wp_rewrite = '';
	private $title = '';
	private $title_callback = '__';
	private $title_arguments = array('');
	private $page_callback = '';
	private $page_arguments = array();
	private $access_callback = TRUE;
	private $access_arguments = array();
	private $template = '';
	private $properties = array();

	/**
	 * @throws Exception
	 * @param string $id A unique string used to refer to this route
	 * @param array $properties An array of key/value pairs used to set
	 * the properties of the route. At a minimum, must include:
	 *  - path
	 *  - page_callback
	 */
	public function __construct( $id, array $properties ) {
		$this->set('id', $id);

		foreach ( array('path', 'page_callback') as $property ) {
			if ( !isset($properties[$property]) || !$properties[$property] ) {
				throw new Exception(self::__("Missing $property"));
			}
		}
		
		foreach ( $properties as $property => $value ) {
			$this->set($property, $value);
		}
		
		if ( $this->access_arguments && $properties['access_callback'] ) {
			$this->set('access_callback', 'current_user_can');
		}

	}

	/**
	 * Get the value of the the given property
	 *
	 * @throws Exception
	 * @param string $property
	 * @return mixed
	 */
	public function get( $property ) {
		if ( isset($this->$property) ) {
			return $this->$property;
		} elseif ( isset($this->properties[$property]) ) {
			return $this->properties[$property];
		} else {
			throw new Exception(self::__("Property not found: $property."));
		}
	}

	/**
	 * Set the value of the given property to $value
	 *
	 * @throws Exception
	 * @param string $property
	 * @param mixed $value
	 * @return void
	 */
	public function set( $property, $value ) {
		if ( in_array($property, array('id', 'path', 'page_callback')) && !$value ) {
			throw new Exception(self::__("Invalid value for $property. Value may not be empty."));
		}
		if ( in_array($property, array('query_vars', 'title_arguments', 'page_arguments', 'access_arguments')) && !is_array($value) ) {
			throw new Exception(self::__("Invalid value for $property: $value. Value must be an array."));
		}
		if ( isset($this->$property) ) {
			$this->$property = $value;
		} else {
			$this->properties[$property] = $value;
		}
	}

	/**
	 * Execute the callback function for this route.
	 *
	 * @param WP $query_vars
	 * @return void
	 */
	public function execute( WP $query ) {
		// check access
		if ( !$this->check_access($query) ) {
			return; // can't get in
		}

		// do the callback
		$page = $this->get_page($query);
		
		// if we have content, set up the page
		if ( $page === FALSE ) {
			return; // callback explicitly told us not to do anything with output
		}

		$title = $this->get_title($query);

		// TODO: set up the page

		// TODO: do something with the template
	}

	private function get_page( WP $query ) {
		if ( !is_callable($this->page_callback) ) {
			return FALSE; // can't call it
		}
		$args = $this->get_query_args($query, 'page');
		ob_start();
		$returned = call_user_func_array($this->page_callback, $args);
		$echoed = ob_get_clean();

		if ( $returned === FALSE ) {
			return FALSE;
		}

		return $echoed.$returned;
	}

	private function get_title( WP $query ) {
		if ( !is_callable($this->title_callback) ) {
			return $this->title; // can't call it
		}
		$args = $this->get_query_args($query, 'title');
		if ( !$args ) {
			$args = array($this->title);
		}
		$title = call_user_func_array($this->title_callback, $args);

		if ( $title === FALSE ) {
			return $this->title;
		}

		return $title;
	}

	private function check_access( WP $query ) {
		if ( $this->access_callback === FALSE ) {
			return FALSE; // nobody gets in
		}
		if ( is_callable($this->access_callback) ) {
			$args = $this->get_query_args($query, 'access');
			return (bool)call_user_func_array($this->access_callback, $args);
		}
		return (bool)$this->access_callback;

	}

	private function get_query_args( WP $query, $callback_type = 'page' ) {
		$property = $callback_type.'_arguments';
		$args = array();
		if ( $this->$property ) {
			foreach ( $this->$property as $query_var ) {
				if ( $this->is_a_query_var($query_var, $query) ) {
					if ( isset($query->query_vars[$query_var]) ) {
						$args[] = $query->query_vars[$query_var];
					} else {
						$args[] = NULL;
					}
				} else {
					$args[] = $query_var;
				}
			}
		}
		return $args;
	}

	private function is_a_query_var( $var, WP $query ) {
		// $query->public_query_vars should be set and filtered before we get here
		if ( in_array($var, $query->public_query_vars) ) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * @return array WordPress rewrite rules that should point to this instance's callback
	 */
	public function rewrite_rules() {
		$this->generate_rewrite();
		return array(
			$this->path => $this->wp_rewrite,
		);
	}

	/**
	 * Generate the WP rewrite rule for this route
	 *
	 * @return void
	 */
	private function generate_rewrite() {
		$rule = "index.php?";
		$vars = array();
		foreach ( $this->query_vars as $var => $value ) {
			if ( is_int($value) ) {
				$vars[] = $var.'='.$this->preg_index($value);
			} else {
				$vars[] = $var.'='.$value;
			}
		}
		$vars[] = self::QUERY_VAR.'='.$this->id;
		$rule .= implode('&', $vars);
		$this->wp_rewrite = $rule;
	}

	/**
	 * Pass an integer through $wp_rewrite->preg_index()
	 *
	 * @param int $matches
	 * @return string
	 */
	protected function preg_index( $int ) {
		global $wp_rewrite;
		return $wp_rewrite->preg_index($int);
	}
}
