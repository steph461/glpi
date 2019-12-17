<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
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

/**
 * Update from 9.4.3 to 9.4.5
 *
 * @return bool for success (will die for most error)
**/
function update943to945() {
   global $DB, $migration;

   $updateresult     = true;

   //TRANS: %s is the number of new version
   $migration->displayTitle(sprintf(__('Update to %s'), '9.4.5'));
   $migration->setVersion('9.4.5');

   /** Add OLA TTR begin date field to Tickets */
   $iterator = new DBmysqlIterator(null);
   $migration->addField(
      'glpi_tickets',
      'ola_ttr_begin_date',
      'datetime',
      [
         'after'     => 'olalevels_id_ttr',
         'update'    => $DB->quoteName('date'), // Assign ticket creation date by default
         'condition' => 'WHERE ' . $iterator->analyseCrit(['NOT' => ['olas_id_ttr' => '0']])
      ]
   );
   /** /Add OLA TTR begin date field to Tickets */

   /** Fix language fields */
   $translatable_tables = [
      'glpi_dropdowntranslations'             => 'DEFAULT NULL',
      'glpi_knowbaseitemtranslations'         => 'DEFAULT NULL',
      'glpi_notificationtemplatetranslations' => "NOT NULL DEFAULT ''",
      'glpi_knowbaseitems_revisions'          => 'DEFAULT NULL',
      'glpi_knowbaseitems_comments'           => 'DEFAULT NULL',
   ];
   foreach ($translatable_tables as $table => $default) {
      $migration->changeField(
         $table,
         'language',
         'language',
         'varchar(10) COLLATE utf8_unicode_ci ' . $default
      );
      $migration->addPostQuery(
         $DB->buildUpdate(
            $table,
            ['language' => 'es_419'],
            ['language' => 'es_41']
         )
      );
   }
   /** /Fix language fields */

   /** Password expiration policy */
   $migration->addConfig(
      [
         'password_expiration_delay'      => '-1',
         'password_expiration_notice'     => '-1',
         'password_expiration_lock_delay' => '-1',
      ]
   );
   if (!$DB->fieldExists('glpi_users', 'password_last_update')) {
      $migration->addField(
         'glpi_users',
         'password_last_update',
         'timestamp',
         [
            'null'   => true,
            'after'  => 'password',
         ]
      );
   }
   $passwordexpires_notif_count = countElementsInTable(
      'glpi_notifications',
      [
         'itemtype' => 'User',
         'event'    => 'passwordexpires',
      ]
   );
   if ($passwordexpires_notif_count === 0) {
      $DB->insertOrDie(
         'glpi_notifications',
         [
            'name'            => 'Password expires alert',
            'entities_id'     => 0,
            'itemtype'        => 'User',
            'event'           => 'passwordexpires',
            'comment'         => null,
            'is_recursive'    => 1,
            'is_active'       => 1,
            'date_creation'   => new \QueryExpression('NOW()'),
            'date_mod'        => new \QueryExpression('NOW()'),
         ],
         'Add password expires notification'
      );
      $notification_id = $DB->insert_id();

      $DB->insertOrDie(
         'glpi_notificationtemplates',
         [
            'name'            => 'Password expires alert',
            'itemtype'        => 'User',
            'date_mod'        => new \QueryExpression('NOW()'),
         ],
         'Add password expires notification template'
      );
      $notificationtemplate_id = $DB->insert_id();

      $DB->insertOrDie(
         'glpi_notifications_notificationtemplates',
         [
            'notifications_id'         => $notification_id,
            'mode'                     => Notification_NotificationTemplate::MODE_MAIL,
            'notificationtemplates_id' => $notificationtemplate_id,
         ],
         'Add password expires notification template instance'
      );

      $DB->insertOrDie(
         'glpi_notificationtargets',
         [
            'items_id'         => 19,
            'type'             => 1,
            'notifications_id' => $notification_id,
         ],
         'Add password expires notification targets'
      );

      $DB->insertOrDie(
         'glpi_notificationtemplatetranslations',
         [
            'notificationtemplates_id' => $notificationtemplate_id,
            'language'                 => '',
            'subject'                  => '##user.action##',
            'content_text'             => <<<PLAINTEXT
##user.realname## ##user.firstname##,

##IFuser.password.has_expired=1##
##lang.password.has_expired.information##
##ENDIFuser.password.has_expired##
##ELSEuser.password.has_expired##
##lang.password.expires_soon.information##
##ENDELSEuser.password.has_expired##
##lang.user.password.expiration.date##: ##user.password.expiration.date##
##IFuser.account.lock.date##
##lang.user.account.lock.date##: ##user.account.lock.date##
##ENDIFuser.account.lock.date##

##password.update.link## ##user.password.update.url##
PLAINTEXT
            ,
            'content_html'             => <<<HTML
&lt;p&gt;&lt;strong&gt;##user.realname## ##user.firstname##&lt;/strong&gt;&lt;/p&gt;

##IFuser.password.has_expired=1##
&lt;p&gt;##lang.password.has_expired.information##&lt;/p&gt;
##ENDIFuser.password.has_expired##
##ELSEuser.password.has_expired##
&lt;p&gt;##lang.password.expires_soon.information##&lt;/p&gt;
##ENDELSEuser.password.has_expired##
&lt;p&gt;##lang.user.password.expiration.date##: ##user.password.expiration.date##&lt;/p&gt;
##IFuser.account.lock.date##
&lt;p&gt;##lang.user.account.lock.date##: ##user.account.lock.date##&lt;/p&gt;
##ENDIFuser.account.lock.date##

&lt;p&gt;##lang.password.update.link## &lt;a href="##user.password.update.url##"&gt;##user.password.update.url##&lt;/a&gt;&lt;/p&gt;
HTML
            ,
         ],
         'Add password expires notification template translations'
      );
   }
   CronTask::Register(
      'User',
      'passwordexpiration',
      DAY_TIMESTAMP,
      [
         'mode'  => CronTask::MODE_EXTERNAL,
         'state' => CronTask::STATE_DISABLE,
         'param' => 100,
      ]
   );
   /** /Password expiration policy */

   // ************ Keep it at the end **************
   $migration->executeMigration();

   return $updateresult;
}
