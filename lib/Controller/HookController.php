<?php

namespace OCA\BigBlueButton\Controller;

use OCA\BigBlueButton\AvatarRepository;
use OCA\BigBlueButton\Db\Room;
use OCA\BigBlueButton\Event\MeetingEndedEvent;
use OCA\BigBlueButton\Event\RecordingReadyEvent;
use OCA\BigBlueButton\Service\RoomService;
use OCP\AppFramework\Controller;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

use OCA\BigBlueButton\Service\RecordingReadyService;

class HookController extends Controller {
	/** @var string */
	protected $token;

	/** @var Room|null */
	protected $room;

	/** @var RoomService */
	private $service;

	/** @var AvatarRepository */
	private $avatarRepository;

	/** @var IEventDispatcher */
	private $eventDispatcher;
	
	/** @var RecordingReadyService */
	private $rec_service;	

	public function __construct(
		string $appName,
		IRequest $request,
		RoomService $service,
		AvatarRepository $avatarRepository,
		IEventDispatcher $eventDispatcher,
		RecordingReadyService $rec_service
	) {
		parent::__construct($appName, $request);

		$this->service = $service;
		$this->avatarRepository = $avatarRepository;
		$this->eventDispatcher = $eventDispatcher;
		$this->rec_service = $rec_service;
	}

	public function setToken(string $token): void {
		$this->token = $token;
		$this->room = null;
	}

	public function isValidToken(): bool {
		$room = $this->getRoom();

		return $room !== null;
	}

	/**
	 * @PublicPage
	 *
	 * @NoCSRFRequired
	 *
	 * @return void
	 */
	public function meetingEnded($recordingmarks = false): void {
		$recordingmarks = \boolval($recordingmarks);
		$room = $this->getRoom();

		$this->service->updateRunning($room->getId(), false);

		$this->avatarRepository->clearRoom($room->uid);

		$this->eventDispatcher->dispatch(MeetingEndedEvent::class, new MeetingEndedEvent($room, $recordingmarks));
	}

	/**
	 * @PublicPage
	 *
	 * @NoCSRFRequired
	 *
	 * @return void
	 */
	public function recordingReady(): void {
		//get params from bbb callback
		$recording_params = $this->request->post['signed_parameters'];
		
		$this->eventDispatcher->dispatch(RecordingReadyEvent::class, new RecordingReadyEvent($this->getRoom()));
		//execute download when recording is ready
		$this->rec_service->downloadRecording($recording_params);		
	}

	private function getRoom(): ?Room {
		if ($this->room === null) {
			$this->room = $this->service->findByUid($this->token);
		}

		return $this->room;
	}
}
