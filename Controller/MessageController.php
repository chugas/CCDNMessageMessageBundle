<?php

/*
 * This file is part of the CCDNMessage MessageBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNMessage\MessageBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContext;
use Application\Success\PortalBundle\Exception\InvalidTokenException;

/**
 *
 * @author Reece Fowell <reece@codeconsortium.com>
 * @version 1.0
 */
class MessageController extends ContainerAware {

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RenderResponse
   */
  public function showMessageAction($messageId) {
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have access to this section.');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    //
    // Get all the folders.
    //
        $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());

    $folderManager = $this->container->get('ccdn_message_message.manager.folder');

    if (!$folders) {
      $folderManager->setupDefaults($user->getId())->flush();

      $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());
    }

    //
    // Get the message.
    //
    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new AccessDeniedException('You do not have access to this section.');
      //throw new NotFoundHttpException('No such message found!');
    }

    $currentFolder = $folderManager->getCurrentFolder($folders, $message->getFolder()->getName());

    $quota = $this->container->getParameter('ccdn_message_message.quotas.max_messages');

    $stats = $folderManager->getUsedAllowance($folders, $quota);

    $this->container->get('ccdn_message_message.manager.message')->markAsRead($message)->flush()->updateAllFolderCachesForUser($user);

    $crumbs = $this->container->get('ccdn_component_crumb.trail')
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.message_index', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_index'), "home")
            ->add($message->getFolder()->getName(), $this->container->get('router')->generate('ccdn_message_message_folder_show', array('folderName' => $message->getFolder()->getName())), "folder")
            ->add($message->getSubject(), $this->container->get('router')->generate('ccdn_message_message_mail_show_by_id', array('messageId' => $messageId)), "email");

    return $this->container->get('templating')->renderResponse('CCDNMessageMessageBundle:Message:show.html.' . $this->getEngine(), array(
                'user' => $user,
                'crumbs' => $crumbs,
                'folders' => $folders,
                'used_allowance' => $stats['used_allowance'],
                'total_message_count' => $stats['total_message_count'],
                'message' => $message,
            ));
  }

  /**
   *
   * @access public
   * @param  int $userId
   * @return RedirectResponse|RenderResponse
   */
  public function composeAction($userId) {
    //
    //	Invalidate this action / redirect if user should not have access to it
    //
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      $this->container->get('request')->getSession()->set(SecurityContext::LAST_USERNAME, $this->container->get('request')->get('email'));
      throw new AccessDeniedException('You do not have permission to use this resource!');
    }
    
    if(!$this->container->get('success.security.token')->isValid($this->container->get('request')->get('token', null))){
      throw new InvalidTokenException('You do not have permission to use this resource!');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    //
    // Are we sending this to someone who's 'send message' button we clicked?
    //
    if ($userId) {
      $sendTo = $this->container->get('ccdn_user_user.repository.user')->findOneById($userId);

      $formHandler = $this->container->get('ccdn_message_message.form.handler.message')->setDefaultValues(array('sender' => $user, 'send_to' => $sendTo));
    } else {
      $formHandler = $this->container->get('ccdn_message_message.form.handler.message')->setDefaultValues(array('sender' => $user));
    }

    if (isset($_POST['submit_draft'])) {
      $formHandler->setMode($formHandler::DRAFT);

      if ($formHandler->process()) {
        return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => 'drafts')));
      }
    }

    if (isset($_POST['submit_preview'])) {
      $formHandler->setMode($formHandler::PREVIEW);
    }

    // Flood Control.
    if (!$this->container->get('ccdn_message_message.component.flood_control')->isFlooded()) {
      if (isset($_POST['submit_post'])) {
        if ($formHandler->process()) {
          $this->container->get('ccdn_message_message.component.flood_control')->incrementCounter();

          return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => 'sent')));
        }
      }
    } else {     
      $this->container->get('session')->setFlash('warning', $this->container->get('translator')->trans('ccdn_message_message.flash.send.flood_control', array(), 'CCDNMessageMessageBundle'));
    }

    //
    // Get all the folders.
    //
        $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());

    $folderManager = $this->container->get('ccdn_message_message.manager.folder');

    if (!$folders) {
      $folderManager->setupDefaults($user->getId())->flush();

      $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());
    }

    $quota = $this->container->getParameter('ccdn_message_message.quotas.max_messages');

    $stats = $folderManager->getUsedAllowance($folders, $quota);

    // setup crumb trail.
    $crumbs = $this->container->get('ccdn_component_crumb.trail')
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.message_index', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_index'), "home")
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.compose_message', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_mail_compose'), "edit");

    return $this->container->get('templating')->renderResponse('CCDNMessageMessageBundle:Message:compose.html.' . $this->getEngine(), array(
                'crumbs' => $crumbs,
                'form' => $formHandler->getForm()->createView(),
                'preview' => $formHandler->getForm()->getData(),
                'folders' => $folders,
                'used_allowance' => $stats['used_allowance'],
                'total_message_count' => $stats['total_message_count'],
                'user' => $user,
            ));
  }

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RedirectResponse|RenderResponse
   */
  public function replyAction($messageId) {
    //
    //	Invalidate this action / redirect if user should not have access to it
    //
        if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have permission to use this resource!');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    $formHandler = $this->container->get('ccdn_message_message.form.handler.message')->setDefaultValues(array('sender' => $user, 'message' => $message, 'action' => 'reply'));

    // Flood Control.
    if (!$this->container->get('ccdn_message_message.component.flood_control')->isFlooded()) {
      if ($formHandler->process()) {
        $this->container->get('ccdn_message_message.component.flood_control')->incrementCounter();

        $this->container->get('session')->setFlash('notice', $this->container->get('translator')->trans('ccdn_message_message.flash.message.sent.success', array(), 'CCDNMessageMessageBundle'));

        return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => 'sent')));
      }
    } else {
      $this->container->get('session')->setFlash('warning', $this->container->get('translator')->trans('ccdn_message_message.flash.send.flood_control', array(), 'CCDNMessageMessageBundle'));
    }

    //
    // Get all the folders.
    //
        $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());

    $folderManager = $this->container->get('ccdn_message_message.manager.folder');

    if (!$folders) {
      $folderManager->setupDefaults($user->getId())->flush();

      $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());
    }

    $quota = $this->container->getParameter('ccdn_message_message.quotas.max_messages');

    $stats = $folderManager->getUsedAllowance($folders, $quota);

    // setup crumb trail.
    $crumbs = $this->container->get('ccdn_component_crumb.trail')
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.message_index', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_index'), "home")
            ->add($message->getSubject(), $this->container->get('router')->generate('ccdn_message_message_mail_show_by_id', array('messageId' => $messageId)), "email")
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.compose_reply', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_mail_compose_reply', array('messageId' => $messageId)), "edit");

    return $this->container->get('templating')->renderResponse('CCDNMessageMessageBundle:Message:compose.html.' . $this->getEngine(), array(
                'crumbs' => $crumbs,
                'form' => $formHandler->getForm()->createView(),
                'preview' => $formHandler->getForm()->getData(),
                'folders' => $folders,
                'used_allowance' => $stats['used_allowance'],
                'total_message_count' => $stats['total_message_count'],
                'user' => $user,
            ));
  }

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RedirectResponse|RenderResponse
   */
  public function forwardAction($messageId) {
    //
    //	Invalidate this action / redirect if user should not have access to it
    //
        if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have permission to use this resource!');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    $formHandler = $this->container->get('ccdn_message_message.form.handler.message')->setDefaultValues(array('sender' => $user, 'message' => $message, 'action' => 'forward'));

    // Flood Control.
    if (!$this->container->get('ccdn_message_message.component.flood_control')->isFlooded()) {
      if ($formHandler->process()) {
        $this->container->get('ccdn_message_message.component.flood_control')->incrementCounter();

        $this->container->get('session')->setFlash('notice', $this->container->get('translator')->trans('ccdn_message_message.flash.message.sent.success', array(), 'CCDNMessageMessageBundle'));

        return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => 'sent')));
      }
    } else {
      $this->container->get('session')->setFlash('warning', $this->container->get('translator')->trans('ccdn_message_message.flash.send.flood_control', array(), 'CCDNMessageMessageBundle'));
    }

    //
    // Get all the folders.
    //
		$folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());

    $folderManager = $this->container->get('ccdn_message_message.manager.folder');

    if (!$folders) {
      $folderManager->setupDefaults($user->getId())->flush();

      $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());
    }

    $quota = $this->container->getParameter('ccdn_message_message.quotas.max_messages');

    $stats = $folderManager->getUsedAllowance($folders, $quota);

    // setup crumb trail.
    $crumbs = $this->container->get('ccdn_component_crumb.trail')
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.message_index', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_index'), "home")
            ->add($message->getSubject(), $this->container->get('router')->generate('ccdn_message_message_mail_show_by_id', array('messageId' => $messageId)), "email")
            ->add($this->container->get('translator')->trans('ccdn_message_message.crumbs.compose_forward', array(), 'CCDNMessageMessageBundle'), $this->container->get('router')->generate('ccdn_message_message_mail_compose_forward', array('messageId' => $messageId)), "edit");

    return $this->container->get('templating')->renderResponse('CCDNMessageMessageBundle:Message:compose.html.' . $this->getEngine(), array(
                'crumbs' => $crumbs,
                'form' => $formHandler->getForm()->createView(),
                'preview' => $formHandler->getForm()->getData(),
                'folders' => $folders,
                'used_allowance' => $stats['used_allowance'],
                'total_message_count' => $stats['total_message_count'],
                'user' => $user,
            ));
  }

  /**
   * @access public
   * @param int $messageId
   * @return RedirectResponse
   */
  public function sendDraftAction($messageId) {
    //
    //	Invalidate this action / redirect if user should not have access to it
    //
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have permission to use this resource!');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    // Flood Control.
    if (!$this->container->get('ccdn_message_message.component.flood_control')->isFlooded()) {
      $this->container->get('ccdn_message_message.component.flood_control')->incrementCounter();

      $this->container->get('ccdn_message_message.manager.message')->sendDraft(array($message))->flush();
    } else {
      $this->container->get('session')->setFlash('warning', $this->container->get('translator')->trans('ccdn_message_message.flash.send.flood_control', array(), 'CCDNMessageMessageBundle'));
    }

    return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => 'sent')));
  }

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RedirectResponse
   */
  public function markAsReadAction($messageId) {
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have access to this section.');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    $this->container->get('ccdn_message_message.manager.message')->markAsRead($message)->flush()->updateAllFolderCachesForUser($user);

    return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => $message->getFolder()->getName())));
  }

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RedirectResponse
   */
  public function markAsUnreadAction($messageId) {
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have access to this section.');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    $this->container->get('ccdn_message_message.manager.message')->markAsUnread($message)->flush()->updateAllFolderCachesForUser($user);

    return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => $message->getFolder()->getName())));
  }

  /**
   *
   * @access public
   * @param  int $messageId
   * @return RedirectResponse
   */
  public function deleteAction($messageId) {
    if (!$this->container->get('security.context')->isGranted('ROLE_USER')) {
      throw new AccessDeniedException('You do not have access to this section.');
    }

    $user = $this->container->get('security.context')->getToken()->getUser();

    $message = $this->container->get('ccdn_message_message.repository.message')->findMessageByIdForUser($messageId, $user->getId());
    $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());

    if (!$folders) {
      $this->container->get('ccdn_message_message.manager.folder')->setupDefaults($user->getId())->flush();

      $folders = $this->container->get('ccdn_message_message.repository.folder')->findAllFoldersForUser($user->getId());
    }

    if (!$message) {
      throw new NotFoundHttpException('No such message found!');
    }

    $this->container->get('ccdn_message_message.manager.message')->delete($message, $folders)->flush()->updateAllFolderCachesForUser($user);

    return new RedirectResponse($this->container->get('router')->generate('ccdn_message_message_folder_show', array('folder_name' => $message->getFolder()->getName())));
  }

  /**
   *
   * @access protected
   * @return string
   */
  protected function getEngine() {
    return $this->container->getParameter('ccdn_message_message.template.engine');
  }

}
