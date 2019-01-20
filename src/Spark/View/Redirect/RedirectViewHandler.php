<?php
/**
 *
 *
 * Date: 06.07.14
 * Time: 18:11
 */

namespace Spark\View\Redirect;


use Spark\Core\Annotation\Inject;
use Spark\Http\Request;
use Spark\Routing;
use Spark\Core\Routing\RequestData;
use Spark\Utils\Collections;
use Spark\Utils\StringUtils;
use Spark\Utils\UrlUtils;
use Spark\View\ViewHandler;

class RedirectViewHandler extends ViewHandler {

    public const NAME = 'redirectViewHandler';

    /**
     * Inject
     * @var Routing
     */
    private $routing;


    public function isView($viewModel): bool {
        return $viewModel instanceof RedirectViewModel;
    }

    /**
     * @param RedirectViewModel $viewModel
     * @param Request|RequestData $request
     */
    public function handleView($viewModel, RequestData $request): void {
        if ($this->isView($viewModel)) {

            $redirect = $viewModel->getUrl();
            if (StringUtils::isNotBlank($redirect)) {

                $resolved = Routing::get()->resolveRoute($redirect, $viewModel->getParams());

                if ($resolved !== $redirect) {
                    $request->instantRedirect($resolved);
                } else {

                    if (Collections::isNotEmpty($viewModel->getParams())) {
                        $redirect = UrlUtils::appendParams($redirect, $viewModel->getParams());
                    }

                    $request->instantRedirect($redirect);
                }
            }
        }
    }
}