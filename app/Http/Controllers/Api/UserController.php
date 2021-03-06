<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\LogReputationRepository;
use App\Contracts\Repositories\UserRepository;
use App\Exceptions\Api\ActionException;
use App\Http\Requests\Api\User\AddTagsRequest;
use App\Http\Requests\Api\User\SearchUserRequest;
use App\Http\Requests\Api\Follow\FollowRequest;
use App\Exceptions\Api\NotFoundException;
use App\Exceptions\Api\UnknownException;
use Illuminate\Support\Facades\Log;

class UserController extends ApiController
{
    protected $bookSelect = [
        'books.id',
        'books.title',
        'description',
        'author',
        'code',
        'publish_date',
        'avg_star',
        'total_page',
        'count_view',
        'category_id',
        'office_id'
    ];

    protected $updateBookSelect = [
        'id',
        'user_id',
        'book_id',
        'title',
        'description',
        'author',
        'publish_date',
        'category_id',
        'office_id',
        'created_at'
    ];

    protected $imageSelect = [
        'id',
        'path',
        'size',
        'thumb_path',
        'target_id',
        'target_type',
    ];

    protected $categorySelect = [
        'id',
        'name_vi',
        'name_en',
        'name_jp',
    ];

    protected $officeSelect = [
        'id',
        'name',
    ];

    protected $ownerSelect = [
        'id',
        'name',
        'avatar',
        'position',
    ];

    protected $relations = [];

    public function __construct(UserRepository $repository)
    {
        parent::__construct($repository);

        $this->relations = [
            'image' => function ($q) {
                $q->select($this->imageSelect);
            },
            'category' => function ($q) {
                $q->select($this->categorySelect);
            },
            'office' => function ($q) {
                $q->select($this->officeSelect);
            },
            'owners' => function ($q) {
                $q->select($this->ownerSelect);
            }
        ];
    }

    public function show($id)
    {
        return $this->requestAction(function () use ($id) {
            $this->compacts['item'] = $this->repository->show($id);
            $this->compacts['item']['favorite_categories'] = $this->repository->getFavoriteCategory($id);
        });
    }

    public function getBook($id, $action)
    {
        if (!in_array($action, array_keys(config('model.book_user.status')))
            && $action != config('model.user_sharing_book')
            && $action != config('model.user_reviewed_book')
        ) {
            throw new ActionException;
        }

        return $this->getData(function () use ($id, $action) {
            $data = $this->repository->getDataBookOfUser($id, $action, $this->bookSelect, $this->relations);

            $this->compacts['items'] = $this->reFormatPaginate($data);
        });
    }

    public function getUserFromToken()
    {
        return $this->requestAction(function () {
            $this->compacts['item'] = $this->user;
            $this->compacts['item']['favorite_categories'] = $this->repository->getFavoriteCategory($this->user->id);
        });
    }

    public function addTags(AddTagsRequest $request)
    {
        $data = $request->item;

        return $this->requestAction(function () use ($data) {
            $this->repository->addTags($data['tags']);
        });
    }

    public function getInterestedBooks()
    {
        return $this->requestAction(function () {
            $this->compacts['items'] = $this->reFormatPaginate(
                $this->repository->getInterestedBooks($this->bookSelect, $this->relations)
            );
        });
    }

    public function ownedBooks()
    {
        return $this->requestAction(function () {
            $this->compacts['items'] = $this->reFormatPaginate(
                $this->repository->ownedBooks($this->bookSelect, ['image'])
            );
        });
    }

    public function getListWaitingApprove()
    {
        return $this->getData(function () {
            $data = $this->repository->getListWaitingApprove($this->bookSelect, $this->relations);

            $this->compacts['items'] = $this->reFormatPaginate($data);
        });
    }

    public function getBookApproveDetail($bookId)
    {
        return $this->getData(function () use ($bookId) {
            $this->compacts['item'] = $this->repository->getBookApproveDetail($bookId, $this->bookSelect, $this->relations);
        });
    }

    public function getNotifications()
    {
        return $this->requestAction(function () {
            $this->compacts['items'] = $this->repository->getNotifications();
        });
    }

    public function getNotificationsDropdown()
    {
        return $this->requestAction(function () {
            $this->compacts['items']['notification'] = $this->repository->getNotificationsDropdown();
        });
    }

    public function followOrUnfollow(FollowRequest $request, LogReputationRepository $logReputationRepository)
    {
        $data = $request->all();

        return $this->requestAction(function () use ($data, $logReputationRepository) {
            $this->repository->followOrUnfollow($data['item']['user_id'], $logReputationRepository);
        });
    }

    public function getFollowInfo($id)
    {
        return $this->requestAction(function () use ($id) {
            $this->compacts['items'] = $this->repository->getFollowInfo($id);
        });
    }

    public function updateViewNotifications($notificationId)
    {
        return $this->requestAction(function () use ($notificationId) {
            $this->repository->updateViewNotifications($notificationId);
        });
    }

    public function getCountNotifications()
    {
        return $this->requestAction(function () {
            $this->compacts['item'] = $this->repository->countNotificationNotView();
        });
    }

    public function updateViewNotificationsAll()
    {
        return $this->requestAction(function () {
            $this->repository->updateViewNotificationsAll();
        });
    }

    public function getWaitingApproveEditBook()
    {
        return $this->requestAction(function () {
            $this->compacts['item'] = $this->repository->getWaitingApproveEditBook($this->updateBookSelect);
        });
    }

    public function getTotalUser()
    {
        return $this->getData(function () {
            $this->compacts['item'] = $this->repository->countRecord();
        });
    }

    public function getUserList()
    {
        return $this->getData(function () {
            $data = $this->repository->getByPage();

            $this->compacts['items'] = $this->reFormatPaginate($data);
        });
    }

    public function searchUser(SearchUserRequest $request)
    {
        $data = $request->only(['key', 'type', 'page']);

        return $this->getData(function () use ($data) {
            $data = $this->repository->search($data);

            $this->compacts['items'] = $this->reFormatPaginate($data);
        });
    }

    public function getUserDetail($userId)
    {
        $this->compacts['item'] = $this->repository->getDetail($userId);

        return $this->jsonRender();
    }

    public function setRoleUser($id, $role)
    {
        try {
            return $this->doAction(function () use ($id, $role) {
                $user = $this->repository->findOrFail($id);

                $this->repository->setRole($user, $role);
            }, __FUNCTION__);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());

            throw new NotFoundException();
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            throw new UnknownException($e->getMessage(), $e->getCode());
        }
    }
}
