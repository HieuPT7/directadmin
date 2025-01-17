<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Users;

use Omines\DirectAdmin\Context\ResellerContext;
use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\DirectAdmin;
use Omines\DirectAdmin\DirectAdminException;
use Omines\DirectAdmin\Objects\Database;
use Omines\DirectAdmin\Objects\Domain;
use Omines\DirectAdmin\Objects\BaseObject;
use Omines\DirectAdmin\Utility\Conversion;

/**
 * User.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class User extends BaseObject
{
    const CACHE_CONFIG = 'config';
    const CACHE_DATABASES = 'databases';
    const CACHE_USAGE = 'usage';
    const CACHE_DOMAIN_OWNERS = 'domain_owners';

    /** @var Domain[] * */
    private $domains;

    /** @var Database[] * */
    private $databases;

    /**
     * Construct the object.
     *
     * @param string $name Username of the account
     * @param UserContext $context The context managing this object
     * @param mixed|null $config An optional preloaded configuration
     */
    public function __construct($name, UserContext $context, $config = null)
    {
        parent::__construct($name, $context);
        if (isset($config)) {
            $this->setCache(static::CACHE_CONFIG, $config);
        }
    }

    /**
     * Clear the object's internal cache.
     */
    public function clearCache()
    {
        unset($this->domains);
        parent::clearCache();
    }

    /**
     * Creates a new database under this user.
     *
     * @param string $name Database name, without <user>_ prefix
     * @param string $username Username to access the database with, without <user>_ prefix
     * @param string|null $password Password, or null if database user already exists
     * @return Database Newly created database
     */
    public function createDatabase($name, $username, $password = null)
    {
        $db = Database::create($this->getSelfManagedUser(), $name, $username, $password);
        $this->clearCache();
        return $db;
    }

    /**
     * Creates a new domain under this user.
     *
     * @param string $domainName Domain name to create
     * @param float|null $bandwidthLimit Bandwidth limit in MB, or NULL to share with account
     * @param float|null $diskLimit Disk limit in MB, or NULL to share with account
     * @param bool|null $ssl Whether SSL is to be enabled, or NULL to fallback to account default
     * @param bool|null $php Whether PHP is to be enabled, or NULL to fallback to account default
     * @param bool|null $cgi Whether CGI is to be enabled, or NULL to fallback to account default
     * @return Domain Newly created domain
     */
    public function createDomain($domainName, $bandwidthLimit = null, $diskLimit = null, $ssl = null, $php = null, $cgi = null)
    {
        $domain = Domain::create($this->getSelfManagedUser(), $domainName, $bandwidthLimit, $diskLimit, $ssl, $php, $cgi);
        $this->clearCache();
        return $domain;
    }

    /**
     * @return string The username
     */
    public function getUsername()
    {
        return $this->getName();
    }

    /**
     * Returns the bandwidth limit of the user.
     *
     * @return float|null Limit in megabytes, or null for unlimited
     */
    public function getBandwidthLimit()
    {
        return floatval($this->getConfig('bandwidth')) ?: null;
    }

    /**
     * Returns the current period's bandwidth usage in megabytes.
     *
     * @return float
     */
    public function getBandwidthUsage()
    {
        return floatval($this->getUsage('bandwidth'));
    }

    /**
     * Returns the database quota of the user.
     *
     * @return int|null Limit, or null for unlimited
     */
    public function getDatabaseLimit()
    {
        return intval($this->getConfig('mysql')) ?: null;
    }

    /**
     * Returns the current number databases in use.
     *
     * @return int
     */
    public function getDatabaseUsage()
    {
        return intval($this->getUsage('mysql'));
    }

    /**
     * Returns the disk quota of the user.
     *
     * @return float|null Limit in megabytes, or null for unlimited
     */
    public function getDiskLimit()
    {
        return floatval($this->getConfig('quota')) ?: null;
    }

    /**
     * Returns the current disk usage in megabytes.
     *
     * @return float
     */
    public function getDiskUsage()
    {
        return floatval($this->getUsage('quota'));
    }

    /**
     * @return Domain|null The default domain for the user, if any
     */
    public function getDefaultDomain()
    {
        if (empty($name = $this->getConfig('domain'))) {
            return null;
        }
        return $this->getDomain($name);
    }

    /**
     * Returns maximum number of domains allowed to this user, or NULL for unlimited.
     *
     * @return int|null
     */
    public function getDomainLimit()
    {
        return intval($this->getConfig('vdomains')) ?: null;
    }

    /**
     * Returns number of domains owned by this user.
     *
     * @return int
     */
    public function getDomainUsage()
    {
        return intval($this->getUsage('vdomains'));
    }

    /**
     * Returns the database disk quota of the user.
     *
     * @return float|null Limit in megabytes, or null for unlimited
     */
    public function getDbQuotaLimit()
    {
        return floatval($this->getConfig('db_quota')) ?: null;
    }

    /**
     * Returns the current database disk usage in megabytes.
     *
     * @return float
     */
    public function getDbQuotaUsage()
    {
        return floatval($this->getUsage('db_quota'));
    }

    /**
     * Returns whether the user is currently suspended.
     *
     * @return bool
     */
    public function isSuspended()
    {
        return Conversion::toBool($this->getConfig('suspended'));
    }

    /**
     * @param string $domainName
     * @return null|Domain
     */
    public function getDomain($domainName)
    {
        if (!isset($this->domains)) {
            $this->getDomains();
        }
        return isset($this->domains[$domainName]) ? $this->domains[$domainName] : null;
    }

    /**
     * @return Domain[]
     */
    public function getDomains()
    {
        if (!isset($this->domains)) {
            if (!$this->isSelfManaged()) {
                $this->domains = $this->impersonate()->getDomains();
            } else {
                $this->domains = BaseObject::toRichObjectArray($this->getContext()->invokeApiGet('ADDITIONAL_DOMAINS'), Domain::class, $this->getContext());
            }
        }
        return $this->domains;
    }

    /**
     * @return string The user type, as one of the ACCOUNT_TYPE_ constants in the DirectAdmin class
     */
    public function getType()
    {
        return $this->getConfig('usertype');
    }

    /**
     * @return bool Whether the user can use CGI
     */
    public function hasCGI()
    {
        return Conversion::toBool($this->getConfig('cgi'));
    }

    /**
     * @return bool Whether the user can use PHP
     */
    public function hasPHP()
    {
        return Conversion::toBool($this->getConfig('php'));
    }

    /**
     * @return bool Whether the user can use SSL
     */
    public function hasSSL()
    {
        return Conversion::toBool($this->getConfig('ssl'));
    }

    /**
     * @return UserContext
     */
    public function impersonate()
    {
        /** @var ResellerContext $context */
        if (!($context = $this->getContext()) instanceof ResellerContext) {
            throw new DirectAdminException('You need to be at least a reseller to impersonate');
        }
        return $context->impersonateUser($this->getUsername());
    }

    /**
     * Modifies the configuration of the user. For available keys in the array check the documentation on
     * CMD_API_MODIFY_USER in the linked document.
     *
     * @param array $newConfig Associative array of values to be modified
     * @url http://www.directadmin.com/api.html#modify
     */
    public function modifyConfig(array $newConfig)
    {
        $this->getContext()->invokeApiPost('MODIFY_USER', array_merge(
                $this->loadConfig(),
                Conversion::processUnlimitedOptions($newConfig),
                ['action' => 'customize', 'user' => $this->getUsername()]
        ));
        $this->clearCache();
    }

    /**
     * @param bool $newValue Whether catch-all email is enabled for this user
     */
    public function setAllowCatchall($newValue)
    {
        $this->modifyConfig(['catchall' => Conversion::onOff($newValue)]);
    }

    /**
     * @param float|null $newValue New value, or NULL for unlimited
     */
    public function setBandwidthLimit($newValue)
    {
        $this->modifyConfig(['bandwidth' => isset($newValue) ? floatval($newValue) : null]);
    }

    /**
     * @param float|null $newValue New value, or NULL for unlimited
     */
    public function setDiskLimit($newValue)
    {
        $this->modifyConfig(['quota' => isset($newValue) ? floatval($newValue) : null]);
    }

    /**
     * @param int|null $newValue New value, or NULL for unlimited
     */
    public function setDomainLimit($newValue)
    {
        $this->modifyConfig(['vdomains' => isset($newValue) ? intval($newValue) : null]);
    }

    /**
     * Constructs the correct object from the given user config.
     *
     * @param array $config The raw config from DirectAdmin
     * @param UserContext $context The context within which the config was retrieved
     * @return Admin|Reseller|User The correct object
     * @throws DirectAdminException If the user type could not be determined
     */
    public static function fromConfig($config, UserContext $context)
    {
        $name = $config['username'];
        switch ($config['usertype']) {
            case DirectAdmin::ACCOUNT_TYPE_USER:
                return new self($name, $context, $config);
            case DirectAdmin::ACCOUNT_TYPE_RESELLER:
                return new Reseller($name, $context, $config);
            case DirectAdmin::ACCOUNT_TYPE_ADMIN:
                return new Admin($name, $context, $config);
            default:
                throw new DirectAdminException("Unknown user type '$config[usertype]'");
        }
    }

    /**
     * Internal function to safe guard config changes and cache them.
     *
     * @param string $item Config item to retrieve
     * @return mixed The value of the config item, or NULL
     */
    public function getConfig($item)
    {
        return $this->getCacheItem(static::CACHE_CONFIG, $item, function () {
            return $this->loadConfig();
        });
    }

    /**
     * Internal function to safe guard usage changes and cache them.
     *
     * @param string $item Usage item to retrieve
     * @return mixed The value of the stats item, or NULL
     */
    public function getUsage($item)
    {
        return $this->getCacheItem(static::CACHE_USAGE, $item, function () {
            return $this->getAllUsage();
        });
    }

    /**
     * @return UserContext The local user context
     */
    protected function getSelfManagedContext()
    {
        return $this->isSelfManaged() ? $this->getContext() : $this->impersonate();
    }

    /**
     * @return User The user acting as himself
     */
    protected function getSelfManagedUser()
    {
        return $this->isSelfManaged() ? $this : $this->impersonate()->getContextUser();
    }

    /**
     * @return bool Whether the account is managing itself
     */
    protected function isSelfManaged()
    {
        return $this->getUsername() === $this->getContext()->getUsername();
    }

    /**
     * Loads the current user configuration from the server.
     *
     * @return array
     */
    protected function loadConfig()
    {
        return $this->getContext()->invokeApiGet('SHOW_USER_CONFIG', ['user' => $this->getUsername()]);
    }

    public function getAllUsage()
    {
        return $this->getCache(static::CACHE_USAGE, function () {
            return $this->getContext()->invokeApiGet('SHOW_USER_USAGE', ['user' => $this->getUsername()]);
        });
    }

    public function getAllConfig()
    {
        return $this->getCache(static::CACHE_CONFIG, function () {
            return $this->loadConfig();
        });
    }
    
    /**
     * @return Databases[]
     */
    public function getDatabases()
    {
        return $this->getCache(self::CACHE_DATABASES, function () {
            $this->databases = [];
            foreach ($this->getSelfManagedContext()->invokeApiGet('DATABASES') as $fullName) {
                list($user, $db) = explode('_', $fullName, 2);
                if ($this->getUsername() != $user) {
                    throw new DirectAdminException('Username incorrect on database ' . $fullName);
                }
                $this->databases[$db] = new Database($db, $this, $this->getSelfManagedContext());
            }
            return $this->databases;
        });
    }

    /**
     * @param string $databaseName
     * @return null|Database
     */
    public function getDatabase($databaseName)
    {
        $databaseName = str_replace($this->getUsername() . '_' , '', $databaseName);
        if (!isset($this->databases)) {
            $this->getDatabases();
        }
        return isset($this->databases[$databaseName]) ? $this->databases[$databaseName] : null;
    }
    
    /**
     * which will make the CMD_API_DOMAIN_OWNERS filter out all other domains
     * Both Admin and Resellers can run this, but the Reseller can only do lookups on domains under their control (including their Users)
     * @return array
     * @link https://www.directadmin.com/features.php?id=1684
     */
    public function getDomainOwns()
    {
        return $this->getCache(self::CACHE_DOMAIN_OWNERS, function () {
            return $this->getSelfManagedContext()->invokeApiGet('DOMAIN_OWNERS');
        });
    }

    /**
     * @return null|string
     */
    public function getDomainOwn($domain)
    {
        return $this->getCacheItem(static::CACHE_DOMAIN_OWNERS, $domain, function () {
            return $this->getDomainOwns();
        });
    }

    /**
     * @param string $keyName
     * @return array
     * @link https://www.directadmin.com/features.php?id=1298
     * 
     * return
     * [
     *  "allow_htm" => "no"
     *  "clear_key" => "no"
     *  "created_by" => "172.104.183.55"
     *  "date_created" => "1573376686"
     *  "expiry" => "1573437840"
     *  "key" => ""
     *  "max_uses" => "0"
     *  "uses" => "0"
     * ]
     */
    public function getLoginKey(string $keyName): array
    {
        $results = $this->getCacheItem(static::CACHE_LOGIN_KEY, $keyName, function () {
            return $this->getContext()->invokeApiGet('LOGIN_KEYS', [
                'action' => 'get',
                'json' => 'no',
            ]);
        });

        return Conversion::responseToArray($results);
    }

    public function createLoginKey(string $keyName, string $keyValue, string $password, $options = [])
    {
        $next_day = strtotime('+1 days');
        $hour = date('H', $next_day);
        $minute = date('i', $next_day);
        $month = date('m', $next_day);
        $day = date('d', $next_day);
        $year = date('Y', $next_day);
        $max_uses = 0;

        $values  = array(
            'action' => 'create',
            'keyname' => $keyName,
            'key' => $keyValue,
            'key2' => $keyValue,
            'hour' => $hour,
            'minute' => $minute,
            'month' => $month,
            'day' => $day,
            'year' => $year,
            'max_uses' => $max_uses,
            'ips' => '',
            'clear_key' => 'yes',
            'allow_htm' => 'yes',
            'allow_html' => 'yes',
            'passwd' => $password,
            'never_expires' => 'no',
            'create' => 'Create'
        );

        $options['action'] = 'create';
        $options['create'] = 'Create';
        $options['passwd'] = $password;
        $options['keyname'] = $keyName;
        $options['key'] = $keyValue;
        $options['key2'] = $keyValue;

        $values = array_merge($values, $options);
        return $this->getContext()->invokeApiPost('LOGIN_KEYS', $values);
    }


    public function deleteLoginKey(string $keyName)
    {
        $values  = array(
            'action' => 'select',
            'keyname' => $keyName,
            'select0' => $keyName,
            'delete' => 'Delete'
        );

        return $this->getContext()->invokeApiPost('LOGIN_KEYS', $values);
    }

    /**
     * Modifies the package of the user. For available keys in the array check the documentation on
     * CMD_API_MODIFY_USER in the linked document.
     *
     * @param string $package package name to be modified
     * @url http://www.directadmin.com/api.html#modify
     */
    public function modifyPackage(string $package)
    {
        $this->getContext()->invokeApiPost('MODIFY_USER', array_merge(
                ['action' => 'package', 'user' => $this->getUsername(), 'package' => $package]
        ));
        $this->clearCache();
    }
}
