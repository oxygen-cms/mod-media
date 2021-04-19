<?php


namespace OxygenModule\Media\Controller;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Oxygen\Core\Http\Notification;
use Oxygen\Crud\Controller\BasicCrudApi;
use Oxygen\Crud\Controller\SoftDeleteCrudApi;
use Oxygen\Data\Repository\QueryParameters;
use OxygenModule\Media\Entity\MediaDirectory;
use OxygenModule\Media\Repository\MediaDirectoryRepositoryInterface;
use OxygenModule\Media\Repository\MediaRepositoryInterface;

class MediaDirectoryController extends Controller {

    const PER_PAGE = 1000;
    /**
     * @var MediaDirectoryRepositoryInterface|MediaRepositoryInterface
     */
    private $repository;

    const LANG_MAPPINGS = [
        'resource' => 'Directory',
        'pluralResource' => 'Directories'
    ];

    use BasicCrudApi, SoftDeleteCrudApi {
        SoftDeleteCrudApi::getListQueryParameters insteadof BasicCrudApi;
        SoftDeleteCrudApi::deleteDeleteApi insteadof BasicCrudApi;
    }

    /**
     * Constructs the PagesController.
     *
     * @param MediaDirectoryRepositoryInterface $repository
     */
    public function __construct(MediaDirectoryRepositoryInterface $repository) {
        $this->repository = $repository;
        BasicCrudApi::setupLangMappings(self::LANG_MAPPINGS);
    }

    /**
     * List all entities.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getListApi(Request $request): JsonResponse {
        $items = $this->repository->all($this->getListQueryParameters($request));
        $items = array_map(function(MediaDirectory $item) {
            return $item->toArray();
        }, $items);

        $items[] = [
            'id' => null,
            'name' => '',
            'slug' => '/',
            'fullPath' => '/',
            'parentDirectory' => null
        ];

        // render the list
        return response()->json([
            'items' => $items,
            'status' => Notification::SUCCESS,
        ]);
    }

}
