/*
	This view is used as a template to generate proxy classes code.

	It's best to keep proxy classes as lightweight as possible and keep the bulk
	of the code in the mappers because
	- proxy classes cannot be extended by the user,
	- one proxy class by entity needs to be compiled,
	- the more we put here the closer we get to Active Record and the very same problems
	  we wanted to avoid by using Data Mapper.

	There are two kind of functions here :
	- instance functions : implement active record features,
	- static functions : mapper code that must execute in the scope of the model
	  class to gain access to protected properties,
	- magic functions : __get to implement lazy loading of fields and properties.
*/

<?php
	// Prepare what's needed for parent class constructor override :
	$rc = new ReflectionClass($model_class);
	if ($rc->hasMethod('__construct')) {
		$has_constructor = true;
		foreach($rc->getMethod('__construct')->getParameters() as $parm) {
			$sig_parms[] = ($parm->isPassedByReference() ? '&' : '') .
						   '$' . $parm->getName() .
						   ($parm->isDefaultValueAvailable() ? '=' . var_export($parm->getDefaultValue(), true) : '');
			$call_parms[] =	'$' . $parm->getName();
		}
		$constructor_sig_parms	= implode(', ', $sig_parms);
		$constructor_call_parms	= implode(', ', $call_parms);
	}
	else
		$has_constructor = false;
?>

class <?php echo $proxy_class ?> extends <?php echo $model_class ?> {
	// Best knowledge we have about the state of the object's data in the DB :
	public $glue_db_state = array();

	// Mapper data copied here for convenience :
	static public $glue_entity		= <?php var_export($entity)		?>;	// Entity name
	static public $glue_properties	= <?php var_export($properties)	?>;	// Fields => properties mapping
	static public $glue_types		= <?php var_export($types)		?>;	// Fields => property types mapping

	// Constructor :
	public function __construct(<?php echo $has_constructor ? $constructor_sig_parms : '' ?>) {
		// Unset null variables so that __get is called on first access :
		foreach(get_object_vars($this) as $var => $value)
			if (is_null($value))
				unset($this->$var);

		<?php if ($has_constructor) { ?>
		// Call parent constructor :
		parent::__construct(<?php echo $constructor_call_parms; ?>);
		<?php } ?>
	}

	// Setter for values coming from the database :
	public function glue_set($field, $value) {
		$prop = self::$glue_properties[$field];
		$this->$prop = settype($value, self::$glue_types[$field]);
		$this->glue_db_state[$field] = $this->$prop;
	}

	// Getter :
	public function glue_get($field) {
		return $this->{self::$glue_properties[$field]};
	}

	// Entity mapper :
	public function glue_entity() { return glue::entity(self::$glue_entity); }

	// Active Record features :
	public function delete() { return $this->glue_entity()->delete($this); }
	public function insert() { return $this->glue_entity()->insert($this); }
	public function update() { return $this->glue_entity()->update($this); }

	// Lazy loading of properties and relationships :
	public function __get($var) {
		// __get called even though $var already initialized ?
		$obj_vars = get_object_vars($this);
		if (isset($obj_vars[$var])
			trigger_error("Trying to access protected property $var from outside the class <?php echo $model_class ?>.", E_ERROR);

		// Lazy loading of $var :
		if ($field = array_search($var, self::$glue_properties))
			$this->glue_entity()->load_field($this, $field);
		else
			$this->glue_entity()->load_relationship($this, $var);
			
		return $this->$var;
	}
}