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

namespace CCDNMessage\MessageBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Security\Core\User\UserInterface;

abstract class Message
{
	/** @var CCDNMessage\MessageBundle\Entity\Folder $folder */
    protected $folder = null;

	/** @var UserInterface $sentTo */
    protected $sentTo = null;
	
	/** @var UserInterface $sentFrom */
    protected $sentFrom = null;
	
	/** @var UserInterface $owmedBy */
    protected $ownedBy = null;

	/** @var CCDNMessage\MessageBundle\Entity\Message $inResponseTo */
    protected $inResponseTo = null;
	
	/**
	 *
	 * @access public
	 */
    public function __construct()
    {
        // your own logic
    }

    /**
     * Get folder
     *
     * @return CCDNMessage\MessageBundle\Entity\Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }
		
    /**
     * Set folder
     *
     * @param CCDNMessage\MessageBundle\Entity\Folder $folder
	 * @return Message
     */
    public function setFolder(\CCDNMessage\MessageBundle\Entity\Folder $folder = null)
    {
        $this->folder = $folder;
		
		return $this;
    }

    /**
     * Get sentTo
     *
     * @return UserInterface
     */
    public function getSentTo()
    {
        return $this->sentTo;
    }
	
    /**
     * Set sentTo
     *
     * @param UserInterface $sentTo
	 * @return Message
     */
    public function setSentTo(UserInterface $sentTo = null)
    {
        $this->sentTo = $sentTo;
		
		return $this;
    }

    /**
     * Get sentFrom
     *
     * @return UserInterface
     */
    public function getSentFrom()
    {
        return $this->sentFrom;
    }

    /**
     * Set sentFrom
     *
     * @param UserInterface $sentFrom
	 * @return Message
     */
    public function setSentFrom(UserInterface $sentFrom = null)
    {
        $this->sentFrom = $sentFrom;
		
		return $this;
    }

    /**
     * Get ownedBy
     *
     * @return UserInterface
     */
    public function getOwnedBy()
    {
        return $this->ownedBy;
    }
	
    /**
     * Set ownedBy
     *
     * @param UserInterface $ownedBy
	 * @return Message
     */
    public function setOwnedBy(UserInterface $ownedBy = null)
    {
        $this->ownedBy = $ownedBy;
		
		return $this;
    }
	
    /**
     * Get inResponseTo
     *
     * @return CCDNMessage\MessageBundle\Entity\Message
     */
	public function getInResponseTo()
	{
		return $this->inResponseTo;
	}
	
    /**
     * Set inResponseTo
     *
     * @param CCDNMessage\MessageBundle\Entity\Message $inResponseTo
	 * @return Message
     */
	public function setInResponseTo(\CCDNMessage\MessageBundle\Entity\Message $inResponseTo)
	{
		$this->inResponseTo = $inResponseTo;
		
		return $this;
	}
}
