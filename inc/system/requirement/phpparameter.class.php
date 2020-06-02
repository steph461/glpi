<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2019 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace Glpi\System\Requirement;

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 9.5.0
 */
class PhpParameter extends AbstractRequirement {

   /**
    * GLPI parameter key.
    *
    * @var string
    */
   private $key;

   /**
    * @param string $key  GLPI parameter key
    */
   public function __construct(string $key) {
      $this->title = sprintf(__('Testing PHP parameter %s'), $key);
      $this->key = $key;
   }

   protected function check() {
      $this->validated = ini_get($this->key) && trim(ini_get($this->key)) != '';

      $this->validation_messages[] = $this->validated
         ? sprintf(__('PHP parameter %s is present.'), $this->key)
         : sprintf(__('PHP parameter %s is required.'), $this->key);
   }
}
