<?php
/**
 * LICENSE
 *
 * Copyright © 2016-2017 Teclib'
 * Copyright © 2010-2017 by the FusionInventory Development Team.
 *
 * This file is part of Flyve MDM Plugin for GLPI.
 *
 * Flyve MDM Plugin for GLPI is a subproject of Flyve MDM. Flyve MDM is a mobile
 * device management software.
 *
 * Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @author    Thierry Bugier Pineau
 * @copyright Copyright © 2017 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */
namespace tests\units;

use Glpi\Test\CommonTestCase;

class PluginFlyvemdmAgent extends CommonTestCase {

   /**
    * @var string
    */
   private $minAndroidVersion = '2.0.0';

   public function beforeTestMethod($method) {
      $this->resetState();
      parent::beforeTestMethod($method);
      $this->setupGLPIFramework();
      $this->boolean($this->login('glpi', 'glpi'))->isTrue();
   }

   /**
    * @tags testDeviceCountLimit
    */
   public function testDeviceCountLimit() {
      $this->given(
         $deviceLimit = 5,
         $entityConfig = new \PluginFlyvemdmEntityConfig(),
         $entityConfig->update([
            'id'           => $_SESSION['glpiactive_entity'],
            'device_limit' => $deviceLimit
         ]),
         $invitationData = []
      );

      for ($i = 0; $i <= $deviceLimit; $i++) {
         $email = $this->getUniqueEmail();
         $invitation = new \PluginFlyvemdmInvitation();
         $invitation->add([
            'entities_id'  => $_SESSION['glpiactive_entity'],
            '_useremails'  => $email,
         ]);
         $invitationData[] = ['invitation' => $invitation, 'email' => $email];
      }

      for ($i = 0, $max = (count($invitationData) - 1); $i < $max; $i++) {
         $agentId = $this->loginAndAddAgent($invitationData[$i]);
         // Agent creation should succeed
         $this->integer($agentId)->isGreaterThan(0, json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));
      }

      // One nore ienrollment
      $agentId = $this->loginAndAddAgent($invitationData[$i]);
      // Device limit reached : agent creation should fail
      $this->boolean($agentId)->isFalse();
   }

   /**
    * @tags testEnrollAgent
    */
   public function testEnrollAgent() {
      // Set a computer type
      $computerTypeId = 3;
      \Config::setConfigurationValues('flyvemdm', ['computertypes_id' => $computerTypeId]);

      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());

      $invitationToken = $invitation->getField('invitation_token');
      $inviationId = $invitation->getID();

      // Test enrollment with bad token
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, 'bad token');
      $this->boolean($agent->isNewItem())->isTrue(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Test the invitation log did not increased
      // this happens because the enrollment failed without identifying the invitation
      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount = 0;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test enrollment without MDM type
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, 'bad token');
      $this->boolean($agent->isNewItem())->isTrue(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Test the invitation log did not increased
      // this happens because the enrollment failed without identifying the invitation
      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount = 0;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test enrollment with bad MDM type
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, 'bad token', 'alien MDM');
      $this->boolean($agent->isNewItem())->isTrue(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Test the invitation log did not increased
      // this happens because the enrollment failed without identifying the invitation
      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount = 0;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test enrollment without version
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, 'android', null);
      $this->boolean($agent->isNewItem())->isTrue();

      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount++;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test enrollment with bad version
      $rows = $invitationLog->find("1");
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, 'android', 'bad version');
      $this->boolean($agent->isNewItem())->isTrue();

      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount++;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test enrollment with a too low version
      $rows = $invitationLog->find("1");
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, 'android', '1.9');
      $this->boolean($agent->isNewItem())->isTrue();

      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount++;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // test enrollment without serial or uuid
      $agent = $this->agentFromInvitation($user, $guestEmail, null, $invitationToken);
      $this->boolean($agent->isNewItem())->isTrue();

      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $expectedLogCount++;
      $rows = $invitationLog->find("`plugin_flyvemdm_invitations_id` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test successful enrollment
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, 'apple');
      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Test there is no new entry in the invitation log
      $invitationLog = new \PluginFlyvemdmInvitationlog();
      $fk = \PluginFlyvemdmInvitation::getForeignKeyField();
      $rows = $invitationLog->find("`$fk` = '$inviationId'");
      $this->integer(count($rows))->isEqualTo($expectedLogCount);

      // Test the agent has been enrolled
      $this->string($agent->getField('enroll_status'))->isEqualTo('enrolled');

      // Test the invitation status is updated
      $invitation->getFromDB($invitation->getID());
      $this->string($invitation->getField('status'))->isEqualTo('done');

      // Test a computer is associated to the agent
      $computer = new \Computer();
      $this->boolean($computer->getFromDB($agent->getField(\Computer::getForeignKeyField())))->isTrue();

      // Test the computer has the expected type
      $this->string($computer->getField('computertypes_id'))->isEqualTo($computerTypeId);

      // Test the serial is saved
      $this->string($computer->getField('serial'))->isEqualTo($serial);

      // Test the user of the computer is the user of the invitation
      $this->integer((int) $computer->getField(\User::getForeignKeyField()))->isEqualTo($invitation->getField('users_id'));

      // Test a new user for the agent exists
      $agentUser = new \User();
      $agentUser->getFromDBByCrit(['realname' => $serial]);
      $this->boolean($agentUser->isNewItem())->isFalse();

      // Test the agent user does not have a password
      $this->boolean(empty($agentUser->getField('password')))->isTrue();

      // Test the agent user has an api token
      $this->string($agentUser->getField('api_token'))->isNotEmpty();

      // Create the agent to generate MQTT account
      $agent->getFromDB($agent->getID());

      // Is the mqtt user created and enabled ?
      $mqttUser = new \PluginFlyvemdmMqttuser();
      $this->boolean($mqttUser->getByUser($serial))->isTrue();

      // Check the MQTT user is enabled
      $this->integer((int) $mqttUser->getField('enabled'))->isEqualTo('1');

      // Check the user has ACLs
      $mqttACLs = $mqttUser->getACLs();
      $this->integer(count($mqttACLs))->isEqualTo(4);

      // Check the ACLs
      $validated = 0;
      foreach ($mqttACLs as $acl) {
         if (preg_match("~/agent/$serial/Command/#$~", $acl->getField('topic')) == 1) {
            $this->integer((int) $acl->getField('access_level'))->isEqualTo(\PluginFlyvemdmMqttacl::MQTTACL_READ);
            $validated++;
         } else if (preg_match("~/agent/$serial/Status/#$~", $acl->getField('topic')) == 1) {
            $this->integer((int) $acl->getField('access_level'))->isEqualTo(\PluginFlyvemdmMqttacl::MQTTACL_WRITE);
            $validated++;
         } else if (preg_match("~^/FlyvemdmManifest/#$~", $acl->getField('topic')) == 1) {
            $this->integer((int) $acl->getField('access_level'))->isEqualTo(\PluginFlyvemdmMqttacl::MQTTACL_READ);
            $validated++;
         } else if (preg_match("~/agent/$serial/FlyvemdmManifest/#$~", $acl->getField('topic')) == 1) {
            $this->integer((int) $acl->getField('access_level'))->isEqualTo(\PluginFlyvemdmMqttacl::MQTTACL_WRITE);
            $validated++;
         }
      }
      $this->integer($validated)->isEqualTo(count($mqttACLs));

      // Test getting the agent returns extra data for the device
      $agent->getFromDB($agent->getID());
      $this->array($agent->fields)->hasKeys([
         'certificate',
         'mqttpasswd',
         'topic',
         'broker',
         'port',
         'tls',
         'android_bugcollecctor_url',
         'android_bugcollector_login',
         'android_bugcollector_passwd',
         'version',
         'api_token',
         'mdm_type',
      ]);
      $this->string($agent->getField('mdm_type'))->isEqualTo('apple');

      // Check the invitation is expired
      $this->boolean($invitation->getFromDB($invitation->getID()))->isTrue();

      // Is the token expiry set ?
      $this->string($invitation->getField('expiration_date'))->isEqualTo('0000-00-00 00:00:00');

      // Is the status updated ?
      $this->string($invitation->getField('status'))->isEqualTo('done');

      // Check the invitation cannot be used again
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitationToken, 'apple');

      $this->boolean($agent->isNewItem())->isTrue();
   }

   /**
    * Test enrollment with a UUID instead of a serial
    * @tags testEnrollWithUuid
    */
   public function testEnrollWithUuid() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      // Test the agent is created
      $this->boolean($agent->isNewItem())->isFalse($_SESSION['MESSAGE_AFTER_REDIRECT']);
   }

   /**
    * Test agent unenrollment
    * @tags testUnenrollAgent
    */
   public function testUnenrollAgent() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      // Test the agent is created
      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
                                                  use ($tester, &$mockedAgent) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Unenroll");
         $tester->string($mqttMessage)->isEqualTo(json_encode(['unenroll' => 'now'], JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(1);
      };

      $mockedAgent->update([
         'id'           => $mockedAgent->getID(),
         '_unenroll'    => '',
      ]);

      $this->mock($mockedAgent)->call('notify')->once();
   }

   /**
    * Test deletion of an agent
    * @tags testDelete
    */
   public function testDelete() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->cleanupSubtopics = function() {};

      $deleteSuccess = $mockedAgent->delete(['id' => $mockedAgent->getID()]);

      $this->mock($mockedAgent)->call('cleanupSubtopics')->once();

      // check the agent is deleted
      $this->boolean($deleteSuccess)->isTrue();

      // Check if user has not been deleted
      $this->boolean($user->getFromDb($user->getID()))->isTrue();
   }

   /**
    * Test online status change on MQTT message
    * @tags testDeviceOnlineChange
    */
   public function testDeviceOnlineChange() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      $this->deviceOnlineStatus($agent, 'true', 1);

      $this->deviceOnlineStatus($agent, 'false', 0);
   }

   /**
    * Test online status change on MQTT message
    * @tags testChangeFleet
    */
   public function testChangeFleet() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Create a fleet
      $fleet = new \PluginFlyvemdmFleet();
      $fleet->add([
         'entities_id'  => $_SESSION['glpiactive_entity'],
         'name'         => 'fleet A'
      ]);
      $this->boolean($fleet->isNewItem())->isFalse("Could not create a fleet");

      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
                                                  use ($tester, &$mockedAgent, $fleet) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Subscribe");
         $tester->string($mqttMessage)->isEqualTo(json_encode(['subscribe' => [['topic' => $fleet->getTopic()]]], JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(1);
      };

      $updateSuccess = $mockedAgent->update([
         'id'                          => $agent->getID(),
         'plugin_flyvemdm_fleets_id'   => $fleet->getID()
      ]);
      $this->boolean($updateSuccess)->isTrue("Failed to update the agent");
   }

   /**
    * Test the purge of an agent
    * @tags testPurgeEnroledAgent
    */
   public function testPurgeEnroledAgent() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Get enrolment data to enable the agent's MQTT account
      $this->boolean($agent->getFromDB($agent->getID()))->isTrue();

      // Switch back to registered user
      \Session::destroy();
      $this->boolean(self::login('glpi', 'glpi', true))->isTrue();

      $computerId = $agent->getField(\Computer::getForeignKeyField());
      $mqttUser = new \PluginFlyvemdmMqttuser();
      $this->boolean($mqttUser->getByUser($serial))->isTrue('mqtt user has not been created');

      $this->boolean($agent->delete(['id' => $agent->getID()], 1))->isTrue();

      $this->boolean($mqttUser->getByUser($serial))->isFalse();
      $computer = new \Computer();
      $this->boolean($computer->getFromDB($computerId))->isFalse();

      // Check if user has not been deleted
      $this->boolean($user->getFromDb($user->getID()))->isTrue();
   }

   /**
    * Test the purge of an agent the user must persist if he no longer has any agent
    *
    * @tags purgeAgent
    */
   public function testPurgeAgent() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation(\User::getForeignKeyField());
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));
      $testUserId = $user->getID();

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Get enrolment data to enable the agent's MQTT account
      $this->boolean($agent->getFromDB($agent->getID()))->isTrue();

      // Get the userId of the owner of the device
      $computer = new \Computer();
      $userId = $computer->getField(\User::getForeignKeyField());

      // Switch back to registered user
      \Session::destroy();
      $this->boolean(self::login('glpi', 'glpi', true))->isTrue();

      // Delete shall succeed
      $this->boolean($agent->delete(['id' => $agent->getID()]))->isTrue();

      // Test the agent user is deleted
      $agentUser = new \User();
      $this->boolean($agentUser->getFromDB($agent->getField(\User::getForeignKeyField())))->isFalse();

      // Test the owner user is deleted
      $user = new \User();
      $this->boolean($user->getFromDB($userId))->isFalse();

      // Check if user has not been deleted
      $this->boolean($user->getFromDb($testUserId))->isTrue();
   }

   /**
    * test ping message
    * @tags testPingRequest
    */
   public function testPingRequest() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Get enrolment data to enable the agent's MQTT account
      $this->boolean($agent->getFromDB($agent->getID()))->isTrue();

      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
                                                  use ($tester, &$mockedAgent) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Ping");
         $tester->string($mqttMessage)->isEqualTo(json_encode(['query' => 'Ping'], JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(0);
      };

      $updateSuccess = $mockedAgent->update([
         'id'     => $mockedAgent->getID(),
         '_ping'  => ''
      ]);
      // Update shall fail because the ping answer will not occur
      $this->boolean($updateSuccess)->isFalse();
   }

   /**
    * test geolocate message
    * @tags testGeolocateRequest
    */
   public function testGeolocateRequest() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));
      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Get enrolment data to enable the agent's MQTT account
      $this->boolean($agent->getFromDB($agent->getID()))->isTrue();

      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
                                                  use ($tester, &$mockedAgent) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Geolocate");
         $tester->string($mqttMessage)->isEqualTo(json_encode(['query' => 'Geolocate'], JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(0);
      };

      $updateSuccess = $mockedAgent->update([
         'id'           => $mockedAgent->getID(),
         '_geolocate'   => ''
      ]);
      $this->boolean($updateSuccess)->isFalse("Failed to update the agent");
   }

   /**
    * test inventory message
    * @tagsa testInventoryRequest
    */
   public function testInventoryRequest() {
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));

      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Get enrolment data to enable the agent's MQTT account
      $this->boolean($agent->getFromDB($agent->getID()))->isTrue();

      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
      use ($tester, &$mockedAgent) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Inventory");
         $tester->string($mqttMessage)->isEqualTo(json_encode(['query' => 'Inventory'], JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(0);
      };

      $updateSuccess = $mockedAgent->update([
         'id'           => $agent->getID(),
         '_inventory'   => ''
      ]);

      // Update shall fail because the inventory is not received
      $this->boolean($updateSuccess)->isFalse();
   }

   /**
    * Test lock / unlock
    * @tags testLockAndWipe
    */
   public function testLockAndWipe() {
      global $DB;

      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));
      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      // Test lock and wipe are unset after enrollment
      $this->integer((int) $agent->getField('lock'))->isEqualTo(0);
      $this->integer((int) $agent->getField('wipe'))->isEqualTo(0);

      // Test lock
      $this->lockDevice($agent, true, true);

      // Test wipe
      $this->wipeDevice($agent, true, true);

      // Test cannot unlock a wiped device
      $this->lockDevice($agent, false, true);

      // Force unlock device (directly in DB as this is not allowed)
      $agentTable = \PluginFlyvemdmAgent::getTable();
      $DB->query("UPDATE `$agentTable` SET `wipe` = '0' WHERE `id`=" . $agent->getID());

      // Test cannot unlock a wiped device
      $this->lockDevice($agent, false, false);
   }

   /**
    * test geolocate message
    * @tags testMoveBetweenFleets
    */
   public function testMoveBetweenFleets() {
      // Create an invitation
      list($user, $serial, $guestEmail, $invitation) = $this->createUserInvitation('users_id');
      $agent = $this->agentFromInvitation($user, $guestEmail, $serial, $invitation->getField('invitation_token'));
      $this->boolean($agent->isNewItem())->isFalse(json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));

      $fleet = $this->createFleet();
      $fleetFk = $fleet::getForeignKeyField();

      // add the agent in the fleet
      $this->boolean($agent->update([
         'id'       => $agent->getID(),
         $fleetFk   => $fleet->getID(),
      ]))->isTrue();

      // Move the agent to the default fleet
      $entityId = $_SESSION['glpiactive_entity'];
      $defaultFleet = new \PluginFlyvemdmFleet();
      $this->boolean($defaultFleet->getFromDBByQuery(" WHERE `is_default`='1' AND `entities_id`='$entityId'"))->isTrue();

      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0) {};
      $mockedAgent->update([
         'id'       => $agent->getID(),
         $fleetFk   => $defaultFleet->getID()
      ]);
      $this->mock($mockedAgent)->call('notify')->never();

   }

   /**
    * @return object PluginFlyvemdmFleet mocked
    * @tags createFleet
    */
   private function createFleet() {
      $fleet = $this->newMockInstance(\PluginFlyvemdmFleet::class, '\MyMock');
      $fleet->getMockController()->post_addItem = function() {};
      $fleet->add([
         'entities_id'     => $_SESSION['glpiactive_entity'],
         'name'            => $this->getUniqueString(),
      ]);
      $this->boolean($fleet->isNewItem())->isFalse();

      return $fleet;
   }

   /**
    * Lock or unlock device and check the expected status
    * @param \PluginFlyvemdmAgent $agent
    * @param bool $lock
    * @param bool $expected
    */
   private function lockDevice(\PluginFlyvemdmAgent $agent, $lock = true, $expected = true) {
      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
                                                  use ($tester, &$mockedAgent, $lock) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Lock");
         $message = [
            'lock' => $lock ? 'now' : 'unlock',
         ];
         $tester->string($mqttMessage)->isEqualTo(json_encode($message, JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(1);
      };

      $mockedAgent->update([
         'id'     => $agent->getID(),
         'lock'   => $lock ? '1' : '0'
      ]);

      // Check the lock status is saved
      $agent->getFromDB($agent->getID());
      $this->integer((int) $agent->getField('lock'))->isEqualTo($expected ? 1 : 0);
   }

   /**
    * @param \PluginFlyvemdmAgent $agent
    * @param bool $wipe
    * @param bool $expected
    */
   private function wipeDevice(\PluginFlyvemdmAgent $agent, $wipe = true, $expected = true) {
      $tester = $this;
      $mockedAgent = $this->newMockInstance($this->testedClass());
      $mockedAgent->getFromDB($agent->getID());

      $mockedAgent->getMockController()->notify = function($topic, $mqttMessage, $qos = 0, $retain = 0)
      use ($tester, &$mockedAgent, $wipe) {
         $tester->string($topic)->isEqualTo($mockedAgent->getTopic() . "/Command/Wipe");
         $message = [
            'wipe' => $wipe ? 'now' : 'unwipe', // unwipe not implemented because this is not relevant
         ];
         $tester->string($mqttMessage)->isEqualTo(json_encode($message, JSON_UNESCAPED_SLASHES));
         $tester->integer($qos)->isEqualTo(0);
         $tester->integer($retain)->isEqualTo(1);
      };

      $mockedAgent->update([
         'id'     => $agent->getID(),
         'wipe'   => $wipe ? '1' : '0'
      ]);

      // Check the lock status is saved
      $agent->getFromDB($agent->getID());
      $this->integer((int) $agent->getField('wipe'))->isEqualTo($expected ? 1 : 0);
   }

   /**
    * Create a new invitation
    *
    * @param string $guestEmail
    * @return \PluginFlyvemdmInvitation
    */
   private function createInvitation($guestEmail) {
      $invitation = new \PluginFlyvemdmInvitation();
      $invitation->add([
         'entities_id'  => $_SESSION['glpiactive_entity'],
         '_useremails'  => $guestEmail,
      ]);
      $this->boolean($invitation->isNewItem())->isFalse();

      return $invitation;
   }

   /**
    *
    * Try to enroll an device by creating an agent. If the enrollment fails
    * the agent returned will not contain an ID. To ensore the enrollment succeeded
    * use isNewItem() method on the returned object.
    *
    * @param \User $user
    * @param array $input enrollment data for agent creation
    * @return object
    */
   private function enrollFromInvitation(\User $user, array $input) {
      // Close current session
      \Session::destroy();
      $this->setupGLPIFramework();

      // login as invited user
      $_REQUEST['user_token'] = \User::getToken($user->getID(), 'api_token');
      $this->boolean($this->login('', '', false))->isTrue();
      unset($_REQUEST['user_token']);

      // Try to enroll
      $agent = $this->newTestedInstance();
      $agent->add($input);

      return $agent;
   }

   /**
    * @param $agent
    * @param $mqttStatus
    * @param $expectedStatus
    */
   private function deviceOnlineStatus($agent, $mqttStatus, $expectedStatus) {
      $topic = $agent->getTopic() . '/Status/Online';

      // prepare mock
      $message = ['online'   => $mqttStatus];
      $messageEncoded = json_encode($message, JSON_OBJECT_AS_ARRAY);

      $this->mockGenerator->orphanize('__construct');
      $mqttStub = $this->newMockInstance(\sskaje\mqtt\MQTT::class);
      $mqttStub->getMockController()->__construct = function() {};

      $this->mockGenerator->orphanize('__construct');
      $publishStub = $this->newMockInstance(\sskaje\mqtt\Message\PUBLISH::class);
      $this->calling($publishStub)->getTopic = $topic;
      $this->calling($publishStub)->getMessage = $messageEncoded;

      $mqttHandler = \PluginFlyvemdmMqtthandler::getInstance();
      $mqttHandler->publish($mqttStub, $publishStub);

      // refresh the agent
      $agent->getFromDB($agent->getID());
      $this->variable($agent->getField('is_online'))->isEqualTo($expectedStatus);
   }

   /**
    * @param array $currentInvitation
    * @return int
    */
   private function loginAndAddAgent(array $currentInvitation) {
      $invitation = $currentInvitation['invitation'];
      $email = $currentInvitation['email'];

      // Login as guest user
      $_REQUEST['user_token'] = \User::getToken($invitation->getField('users_id'), 'api_token');
      \Session::destroy();
      $this->boolean($this->login('', '', false))->isTrue();
      unset($_REQUEST['user_token']);

      $agent = $this->newTestedInstance();
      $agentId = $agent->add([
         'entities_id' => $_SESSION['glpiactive_entity'],
         '_email' => $email,
         '_invitation_token' => $invitation->getField('invitation_token'),
         '_serial' => $this->getUniqueString(),
         'csr' => '',
         'firstname' => 'John',
         'lastname' => 'Doe',
         'version' => $this->minAndroidVersion,
         'type' => 'android',
      ]);
      return $agentId;
   }

   /**
    * @param string $userIdField
    * @return array
    */
   private function createUserInvitation($userIdField) {
      // Create an invitation
      $serial = $this->getUniqueString();
      $guestEmail = $this->getUniqueEmail();
      $invitation = $this->createInvitation($guestEmail);
      $user = new \User();
      $user->getFromDB($invitation->getField($userIdField));

      return [$user, $serial, $guestEmail, $invitation];
   }

   /**
    * @param \User $user object
    * @param string $guestEmail
    * @param string|null $serial if null the value is not used
    * @param string $invitationToken
    * @param string $mdmType
    * @param string|null $version if null the value is not used
    * @return object
    */
   private function agentFromInvitation(
      $user,
      $guestEmail,
      $serial,
      $invitationToken,
      $mdmType = 'android',
      $version = ''
   ) {
      //Version change
      $finalVersion = $this->minAndroidVersion;
      if ($version) {
         $finalVersion = $version;
      }
      if (null === $version) {
         $finalVersion = null;
      }

      //$finalVersion = (null === $version) ? null : ((!empty($version)) ? $version : $this->minAndroidVersion);
      //$invitationToken = ($badToken) ? 'bad token' : $invitation->getField('invitation_token');
      $input = [
         'entities_id' => $_SESSION['glpiactive_entity'],
         '_email' => $guestEmail,
         '_invitation_token' => $invitationToken,
         'csr' => '',
         'firstname' => 'John',
         'lastname' => 'Doe',
         'type' => $mdmType,
      ];

      if ($serial) {
         $input['_serial'] = $serial;
      }
      if ($finalVersion) {
         $input['version'] = $finalVersion;
      }

      return $this->enrollFromInvitation($user, $input);
   }
}