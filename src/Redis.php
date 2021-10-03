<?php declare( strict_types=1 );

/*
 * This file is part of PHPneeds.
 *
 * (c) Mertcan Ayhan <mertowitch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phpneeds\Libs
{

    class Redis extends \Redis
    {
        /**
         * @var object
         */
        private static object $config;
        /**
         * @var Redis|null
         */
        private static ?Redis $instance = null;

        private function __construct()
        {
            parent::__construct();
            self::_getConfig();
        }

        private static function _getConfig(): void
        {
            self::$config = include( __DIR__ . '/../../../../confs/conf.redis.php' );
        }

        /**
         * @return Redis|null
         */
        public static function getInstance(): ?Redis
        {
            try
            {
                if ( self::$instance === null )
                {
                    self::$instance = ( new self() )->_getNewInstance();
                }
            }
            catch ( \Exception $e )
            {
                self::$instance = null;
            }

            return self::$instance;
        }

        /**
         * @return Redis
         */
        private function _getNewInstance(): Redis
        {
            try
            {
                $newRedisInstance = new Redis();

                $newRedisInstance->connect( self::$config->HOST, self::$config->PORT, 1, null, 0, 0 );

                $newRedisInstance->setOption( self::OPT_PREFIX, self::$config->PREFIX );

                if ( str_contains( session_save_path(), 'auth=' ) )
                {
                    $newRedisInstance->auth( self::$config->PASS );
                }
            }
            catch ( \Exception $e )
            {
                throw new $e;
            }

            return $newRedisInstance;
        }

        /**
         * @return object
         */
        public function getConfig(): object
        {
            $return = self::$config;

            return $return;
        }

    }
}
