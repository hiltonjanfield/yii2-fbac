<?php
/**
 * User.php
 */

namespace hiltonjanfield\FBAC\models;

/**
 * Class User
 * Extends Yii's default User component with functionality for Flag-Based Access Control.
 * See also \hiltonjanfield\yii2_fbac\Controller for more.
 *
 * Usage:
 * Add to your app's Yii config:
 * 'components' => [
 *     'user' => [
 *         'class' => 'hiltonjanfield\yii2_fbac\User',
 *         // additional options here
 *     ]
 * ]
 * Possible options (and default values):
 * 'source' => 'userFlags' - Property in the identity object containing the user's flags. Can be a string or a relation.
 * 'delimiter' => ' ' - If the source property is a string, this will be used as a delimiter for explode().
 * 'columnName' => 'flag' - If the source property is a relation (array of models), this will be used as the
 *                          column name containing the flag (one per row).
 *
 * @package FBAC
 */
class User extends \yii\web\User
{

    /**
     * @var string Source of the user's current flags (property name or relation name)
     * String identifying the identity object property, which can be a string or a relation (array of models).
     * Defaults to 'flags'.
     */
    private $_source = 'flags';

    /**
     * @var string Delimiter that separates flags if `source` is a string property.
     */
    private $_delimiter = ' ';

    /**
     * @var string Name of the column that holds the flag name if `source` is a related model.
     */
    private $_columnName = 'flag';

    private $_caseSensitive = false;

    /**
     * @var string[] The user's current flags.
     */
    private $_flags;

    /**
     * @var array[] Details on all possible known flags. Populated on demand by getFlagDetails().
     */
    private $_flagDetails;

    private $_flagDetailsSource;

    /**
     * @param array $config
     * @throws \yii\base\ErrorException
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->_flags = [];

        if (!isset($this->identity)) {
            $this->_flags = ['guest'];

            return;
        }

        $flags = $this->identity->{$this->_source};

        if ($flags instanceof \yii\db\ActiveRecord) {
            // If `source` is a model, load from model.
            foreach ($flags as $item) {
                $this->_flags[] = $item->{$this->_columnName};
            }

        } elseif (is_array($flags)) {
            // If `source` is an array for some reason (perhaps pre-processed by a User model?),
            // verify that they are strings and load. We don't want objects, arrays, etc to be loaded in.
            foreach ($flags as $item) {
                if (is_string($item)) {
                    $this->_flags[] = $item;
                }
            }

        } elseif (is_string($flags)) {
            // If `source` is a string (presumably a delimited string), use it.
            $this->_flags = explode($this->_delimiter, $flags);

        } else {
            throw new \yii\base\ErrorException('User flag source is not an acceptable data type.');
        }

        if (!$this->_caseSensitive) {
            $this->_flags = array_map('strtolower', $this->_flags);
        }
    }

    /**
     * @return string[]
     */
    public function getFlags()
    {
        return $this->_flags;
    }

    /**
     * Checks if the user has the specified flag.
     *
     * @param string $flag
     * @return bool
     */
    public function hasFlag($flag)
    {
        return in_array($flag, $this->_flags);
    }

    /**
     * Checks that the user has ALL of the specified flags.
     * Accepts one or more flags as single parameters, or multiple flags as array, or any mixture of both.
     * $user->hasAllFlags('square', ['circle', 'oval'], 'triangle'); is perfectly valid.
     *
     * @param string|array $mixed_strings_and_or_arrays,...
     * @return bool
     */
    public function hasAllFlags($mixed_strings_and_or_arrays)
    {
        $params = func_get_args();

        foreach ($params as $flag) {
            if (is_array($flag)) {
                foreach ($flag as $c) {
                    if (!$this->hasFlag($c)) {
                        return false;
                    }
                }
            } elseif (!$this->hasFlag($flag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks that the user has ANY ONE of the specified flags.
     * Accepts one or more flags as single parameters, or multiple flags as array, or any mixture of both.
     * $user->hasAllFlags('square', ['circle', 'oval'], 'triangle'); is perfectly valid.
     *
     * @param string|array $mixed_strings_and_or_arrays,...
     * @return bool
     */
    public function hasAnyFlag($mixed_strings_and_or_arrays)
    {
        // Accepts single flag, multiple flags as parameters, or multiple flags as array, or a mixture.
        $params = func_get_args();

        foreach ($params as $flag) {
            if (is_array($flag)) {
                foreach ($flag as $c) {
                    if ($this->hasFlag($c)) {
                        return true;
                    }
                }
            } elseif ($this->hasFlag($flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    private function loadFlagDetails()
    {
        $source = $this->_flagDetailsSource;

        if (isset($source)) {
            if ($source instanceof UserFlagDetails) {
                //TODO: Load data from provided model.

            } elseif (is_array($this->_flagDetailsSource)) {
                //TODO: Check that the array has the right data, throw exception if not?
                $this->_flagDetails = $this->_flagDetailsSource;

            } elseif (is_string($this->_flagDetailsSource)) {
                //TODO: Check for possibly specified file and load.

            }
        }

        //TODO: Provide a config option and load these from elsewhere (database? PHP file? Or right in config?)
        //TODO: Change basic arrays to objects?

        return [
            'sitemaster' => [
                'name'           => 'Master Site Access',
                'description'    => 'Provides access to all areas of the site. Can view, edit, and delete almost anything.',
                'grant_requires' => ['sitemaster'],
            ],
            'debug'      => [
                'name'           => 'Enable Debugging Mode',
                'description'    => 'Changes error reporting and logging settings when this user is logged in.',
                'grant_requires' => ['sitemaster'],
            ],
        ];
    }

    /**
     * @param $flag
     * @return array
     */
    public function getFlagDetails($flag)
    {
        if (!isset($this->_flagDetails)) {
            $this->loadFlagDetails();
        }

        if (isset($this->_flagDetails[$flag])) {
            return $this->_flagDetails[$flag];

        } else {
            foreach ($this->_flagDetails as $key => $value) {
                $pos = strpos($key, '*');
                if ($pos > 0) {
                    $pattern = '/' . substr($key, 0, $pos) . '([^:]+)' . substr($key, $pos + 1, strlen($key)) . '/';
                    if (preg_match($pattern, $flag, $match)) {
                        foreach ($value as $k => $v) {
                            $value[$k] = str_replace('*', strtoupper($match[1]), $v);
                        }

                        return $value;
                    }
                }
            }
        }

        return [
            'name'           => 'Unknown Flag',
            'description'    => 'This flag is not defined.',
            'grant_requires' => ['master'],
        ];
    }

    /**
     * @param $flag
     * @return mixed
     */
    public function getFlagName($flag)
    {
        return $this->getFlagDetails($flag)['name'];
    }

    /**
     * @param $flag
     * @return mixed
     */
    public function getFlagDesc($flag)
    {
        return $this->getFlagDetails($flag)['desc'];
    }

    /**
     * @param $flag
     * @return bool
     */
    public function canGrantFlag($flag)
    {
        return $this->hasAnyFlag($this->getFlagDetails($flag)['grant_requires']);
    }
}
