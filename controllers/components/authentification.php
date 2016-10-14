<?php
/**
 * Authentification component by Jorge Orpinel
 *
 * (c) 2007 Jorge Orpinel
 *
 * Licencia: Evaluation only.
 * By no means should it be redistributed or copyed.
 *
 * @author Jorge Orpinel
 * @version 1.2
 *
 * For ver 1.5:
 * @ todo usar variable de sesión para el returnURL
 * @ todo redirectTo podría ser una llamada a función
 * @ todo use sessionFlash at login
 *
 * For ver 2.0:
 * @ todo incluir licencia
 * @ todo manejo de cookies
 */
class AuthentificationComponent {

	var $controller;

	function startup(&$controller) {
	  $this->controller =& $controller;
	}

	/**
	 * Verifies the existence of a user with a username and a password.
	 * Assumes there is no repeated usernames in the users model's data.
	 *
	 * Use data['Login']['fromURL'] to tell to which cake's sintaxed URL to return after login.
	 *
	 * @author Jorge Orpinel
	 * @param loginOptions is an array with the following default, overwritable structure:
	 *
	 * redirectTo => '/'						// Cake's sintaxed URL to redirect to if user is alrady logged-in
	 * userModelPtr => (&) $this->controller->Users	// Pointer to Cake's users model class
	 * username => 'username'				// data[modelname] variable containing the username or similar
	 * password => 'password'				// data[modelname] variable containing the password or similar
	 * encoding => null							// XXX solo jala md5 por ahora
	 * avoidReturn => false					// Use to ensure no returnURLs are used
	 * failed->postFailure => null	// Function to call after failed login, before flash or redirect
	 * failed->invalidate => null		// Extra tagErrorMsg name to invalidate, ignored if renderView is false
	 * failed->renderView => true		// If false, in a failed auth. the component uses the next 2 values
	 * failed->sessionErr => null		// Session variable used to detect a login error
	 * failed->flashMsg => null			// If false, redirect is used. Ignored if URL is used
	 * failed->URL => '/'						// Cake's sintaxed URL to flash or redirect on failed auth.
	 * succeded->postLogin => null	// Function to call after succesful login, before flash or redirect
	 * succeded->flashMsg => null		// Same as failed->flashMsg, applying to succesful auth.
	 * succeded->URL => '/'					// Reprocical
	 * sessionVar => 'userId'				// Session variable to write user id to
	 *
	 * Not all values must be overwriten when overwriting some, the rest will be default valued.
	 */
	function login($loginOptions = null) {
	  // Usage validation:
	  if($this->controller->Session->check(
	    isset($loginOptions['sessionVar'])?$loginOptions['sessionVar']:'userId'))
	    $this->controller->redirect(isset($loginOptions['redirectTo'])?$loginOptions['redirectTo']:'/');

	  // Renders the (empty) login view:
	  if(empty($this->controller->data))
	  {
	    $this->controller->render();
	    return;
	  }

	  $data;
	  $options;
	  $user;
	  $username;
	  $password;
	  $succeded;

	  $data =& $this->controller->data;

	  // Login options:
	  $options = array(
	    'userModelPtr' => isset($loginOptions['userModelPtr'])?$loginOptions['userModelPtr']:$this->User,
	    'username' => isset($loginOptions['username'])?$loginOptions['username']:'username',
	    'password' => isset($loginOptions['password'])?$loginOptions['password']:'password',
	    'encoding' => isset($loginOptions['encoding'])?$loginOptions['encoding']:null,
	    'avoidReturn' => isset($loginOptions['avoidReturn'])?$loginOptions['avoidReturn']:false,
	    'failed' => array(
	      'postFailure' =>
	        isset($loginOptions['failed']['postFailure'])?$loginOptions['failed']['postFailure']:null,
	      'invalidate' =>
	        isset($loginOptions['failed']['invalidate'])?$loginOptions['failed']['invalidate']:null,
	      'renderView' =>
	        isset($loginOptions['failed']['renderView'])?$loginOptions['failed']['renderView']:true,
	      'sessionErr' =>
	        isset($loginOptions['failed']['sessionErr'])?$loginOptions['failed']['sessionErr']:null,
	      'flashMsg' => isset($loginOptions['failed']['flashMsg'])?$loginOptions['failed']['flashMsg']:null,
	      'URL' => isset($loginOptions['failed']['URL'])?$loginOptions['failed']['URL']:'/'
	    ),
	    'succeded' => array(
	      'postLogin' =>
	        isset($loginOptions['succeded']['postLogin'])?$loginOptions['succeded']['postLogin']:null,
	      'flashMsg' =>
	        isset($loginOptions['succeded']['flashMsg'])?$loginOptions['succeded']['flashMsg']:null,
	      'URL' => isset($loginOptions['succeded']['URL'])?$loginOptions['succeded']['URL']:'/'
	    ),
	    'sessionVar' => isset($loginOptions['sessionVar'])?$loginOptions['sessionVar']:'userId'
	  );
	  if($options['userModelPtr'] == null) $options['userModelPtr'] =& $this->controller->User;

	  // Does authentification:

	  $username = trim($data[$options['userModelPtr']->name][$options['username']]);
	  $password = trim($data[$options['userModelPtr']->name][$options['password']]);

	  if($options['encoding'] == 'md5') $password = md5($password);

	  //die($username.' '.$password);

	  // Gets user:
	  if($options['userModelPtr']->validates()) {
		  $user = $options['userModelPtr']->find(  // *Only finds the first match!
		    $options['username']." = '$username' AND ".$options['password']." = '$password'"
		  );
	  }
	  if(empty($user)) $succeded = false;  // Authentification failed.
	  else $succeded = true;               // Authentification succeded.

	  // Writes id session var:
    if($succeded)
      $this->controller->Session->write($options['sessionVar'], $user[$options['userModelPtr']->name]['id']);

    // If failed, renders a view:
    if(!$succeded&&$options['failed']['renderView'])
    {
	    if(isset($data['Login']['fromURL']))                                  // and passes the return URL
	      $this->controller->set('loginFromURL', $data['Login']['fromURL']);

	    $options['userModelPtr']->invalidate($options['failed']['invalidate']);
	    $this->controller->render();
	    return;
    }

    // or, in any case:

    // calls postLogin function
		// XXX Podría mandar el $user a la función.
		if($succeded&&isset($options['succeded']['postLogin']))
		  call_user_func(array($this->controller, $options['succeded']['postLogin']));
		else if(!$succeded&&isset($options['failed']['postLogin']))
		  call_user_func(array($this->controller, $options['failed']['postLogin']));

    // and redirects
		if(!$options['avoidReturn']&&isset($data['Login']['fromURL'])) {  // to rhe return URL
			if(!$succeded&&isset($options['failed']['sessionErr']))         // -and if session messaging is set:
				  $this->controller->Session->write($options['failed']['sessionErr'], true);  // it uses it-
		  if(isset($options[$succeded?'succeded':'failed']['flashMsg'])) $this->controller->flash(
		    $options[$succeded?'succeded':'failed']['flashMsg'],
		    $data['Login']['fromURL']
		  );
		  else {
		    header('Location: '.$data['Login']['fromURL']);
		    exit;
		  }
		}

	  if(isset($options[$succeded?'succeded':'failed']['flashMsg']))
	    $this->controller->flash(                                    // or to the given URL.
		    $options[$succeded?'succeded':'failed']['flashMsg'],
		    $options[$succeded?'succeded':'failed']['URL']
		   );
	  else $this->controller->redirect($options[$succeded?'succeded':'failed']['URL']);
	}

	/**
	 * Destroys cakephp's session and redirects or flashes.
	 * No view rendering.
	 *
	 * Use data['Logout']['fromURL'] to tell to which cake's sintaxed URL to return after logout.
	 *
	 * @author Jorge Orpinel
	 * @param logouOptions is an array with the following default, overwritable-by-parts structure:
	 * avoidReturn => false	// Use to ensure no returnURLs are used
	 * flashMsg => null			// If false, redirect is used. Ignored if return is true
	 * sessionFlash => null	// If set, a SessionHelper::setFlash() method is invoked with it.
	 * URL => '/'						// Ignored if the returnURL is used
	 */
	function logout($logoutOptions = null) {
	  $this->controller->Session->destroy();

	  $options = array(
	    'avoidReturn' => isset($logoutOptions['avoidReturn'])?$logoutOptions['avoidReturn']:false,
	    'flashMsg' => isset($logoutOptions['flashMsg'])?$logoutOptions['flashMsg']:null,
	    'sessionFlash' => isset($logoutOptions['sessionFlash'])?$logoutOptions['sessionFlash']:null,
	    'URL' => isset($logoutOptions['URL'])?$logoutOptions['URL']:'/'
	  );

	  if(isset($options['sessionFlash'])) $this->controller->Session->setFlash($options['sessionFlash']);

	  if(isset($options['flashMsg']))                                         // Flashes
	    if(isset($this->controller->data['Logout']['fromURL']))
	      $this->controller->flash(                                        // to the return URL
	        $options['flashMsg'],
	        $this->controller->data['Logout']['fromURL']);
	    else
	      $this->controller->flash(                                           // or to the option's URL.
	        $options['flashMsg'],
	        $options['URL']);
	  else                                                                    // Redirects
	    if(!$options['avoidReturn']&&isset($this->controller->data['Logout']['fromURL']))
	      header('Location: '.$this->controller->data['Logout']['fromURL']);  // to the return URL
	    else
	      $this->controller->redirect($options['URL']);                       //  or to the option's URL.
	}
}

?>
