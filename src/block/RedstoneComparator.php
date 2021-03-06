<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\tile\Comparator;
use pocketmine\block\utils\BlockDataSerializer;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function assert;

class RedstoneComparator extends Flowable{
	/** @var BlockIdentifierFlattened */
	protected $idInfo;

	/** @var int */
	protected $facing = Facing::NORTH;
	/** @var bool */
	protected $isSubtractMode = false;
	/** @var bool */
	protected $powered = false;
	/** @var int */
	protected $signalStrength = 0;

	public function __construct(BlockIdentifierFlattened $idInfo, string $name, ?BlockBreakInfo $breakInfo = null){
		parent::__construct($idInfo, $name, $breakInfo ?? BlockBreakInfo::instant());
	}

	public function getId() : int{
		return $this->powered ? $this->idInfo->getSecondId() : parent::getId();
	}

	public function readStateFromData(int $id, int $stateMeta) : void{
		$this->facing = BlockDataSerializer::readLegacyHorizontalFacing($stateMeta & 0x03);
		$this->isSubtractMode = ($stateMeta & BlockLegacyMetadata::REDSTONE_COMPARATOR_FLAG_SUBTRACT) !== 0;
		$this->powered = ($id === $this->idInfo->getSecondId() or ($stateMeta & BlockLegacyMetadata::REDSTONE_COMPARATOR_FLAG_POWERED) !== 0);
	}

	public function writeStateToMeta() : int{
		return BlockDataSerializer::writeLegacyHorizontalFacing($this->facing) |
			($this->isSubtractMode ? BlockLegacyMetadata::REDSTONE_COMPARATOR_FLAG_SUBTRACT : 0) |
			($this->powered ? BlockLegacyMetadata::REDSTONE_COMPARATOR_FLAG_POWERED : 0);
	}

	public function getStateBitmask() : int{
		return 0b1111;
	}

	public function readStateFromWorld() : void{
		parent::readStateFromWorld();
		$tile = $this->pos->getWorld()->getTile($this->pos);
		if($tile instanceof Comparator){
			$this->signalStrength = $tile->getSignalStrength();
		}
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		$tile = $this->pos->getWorld()->getTile($this->pos);
		assert($tile instanceof Comparator);
		$tile->setSignalStrength($this->signalStrength);
	}

	/**
	 * TODO: ad hoc, move to interface
	 * @return int
	 */
	public function getFacing() : int{
		return $this->facing;
	}

	/**
	 * TODO: ad hoc, move to interface
	 * @param int $facing
	 */
	public function setFacing(int $facing) : void{
		$this->facing = $facing;
	}

	/**
	 * @return bool
	 */
	public function isSubtractMode() : bool{
		return $this->isSubtractMode;
	}

	/**
	 * @param bool $isSubtractMode
	 */
	public function setSubtractMode(bool $isSubtractMode) : void{
		$this->isSubtractMode = $isSubtractMode;
	}

	/**
	 * @return bool
	 */
	public function isPowered() : bool{
		return $this->powered;
	}

	/**
	 * @param bool $powered
	 */
	public function setPowered(bool $powered) : void{
		$this->powered = $powered;
	}

	/**
	 * @return int
	 */
	public function getSignalStrength() : int{
		return $this->signalStrength;
	}

	/**
	 * @param int $signalStrength
	 */
	public function setSignalStrength(int $signalStrength) : void{
		$this->signalStrength = $signalStrength;
	}

	/**
	 * @return AxisAlignedBB[]
	 */
	protected function recalculateCollisionBoxes() : array{
		return [AxisAlignedBB::one()->trim(Facing::UP, 7 / 8)];
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!$blockReplace->getSide(Facing::DOWN)->isTransparent()){
			if($player !== null){
				$this->facing = Facing::opposite($player->getHorizontalFacing());
			}
			return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		}

		return false;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->isSubtractMode = !$this->isSubtractMode;
		$this->pos->getWorld()->setBlock($this->pos, $this);
		return true;
	}

	public function onNearbyBlockChange() : void{
		if($this->getSide(Facing::DOWN)->isTransparent()){
			$this->pos->getWorld()->useBreakOn($this->pos);
		}
	}

	//TODO: redstone functionality
}
