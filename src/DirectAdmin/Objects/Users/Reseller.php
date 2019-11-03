<?php

/*
 * DirectAdmin API Client
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Omines\DirectAdmin\Objects\Users;

use Omines\DirectAdmin\Context\AdminContext;
use Omines\DirectAdmin\Context\ResellerContext;
use Omines\DirectAdmin\Context\UserContext;
use Omines\DirectAdmin\DirectAdminException;
use Omines\DirectAdmin\Objects\BaseObject;

/**
 * Reseller.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Reseller extends User
{
    const CACHE_CONFIG = 'reseller_config';
    const CACHE_DATABASES = 'reseller_databases';
    const CACHE_USAGE = 'reseller_usage';

    /**
     * {@inheritdoc}
     */
    public function __construct($name, UserContext $context, $config = null)
    {
        parent::__construct($name, $context, $config);
    }

    /**
     * @param string $username
     * @return null|User
     */
    public function getUser($username)
    {
        $users = $this->getUsers();
        return isset($users[$username]) ? $users[$username] : null;
    }

    /**
     * @return User[]
     */
    public function getUsers()
    {
        return BaseObject::toObjectArray($this->getContext()->invokeApiGet('SHOW_USERS', ['reseller' => $this->getUsername()]),
                                     User::class, $this->getContext());
    }

    /**
     * @return ResellerContext
     */
    public function impersonate()
    {
        /** @var AdminContext $context */
        if (!($context = $this->getContext()) instanceof AdminContext) {
            throw new DirectAdminException('You need to be an admin to impersonate a reseller');
        }
        return $context->impersonateReseller($this->getUsername());
    }

    public function getFtpUsage()
    {
        return intval($this->getUsage('ftp'));
    }

    public function getFtpLimit()
    {
        return intval($this->getConfig('ftp'));
    }

    public function getAllUsage()
    {
        return $this->getContext()->invokeApiGet('RESELLER_STATS', ['type' => 'usage']);
    }

    public function getAllLimit()
    {
        return $this->loadConfig();
    }

    /**
     * Internal function to safe guard usage changes and cache them.
     *
     * @author HieuPT: 
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
     * Loads the current user configuration from the server.
     *
     * @return array
     */
    public function loadConfig()
    {
        return $this->getContext()->invokeApiGet('RESELLER_STATS', ['type' => '']);
    }
}
