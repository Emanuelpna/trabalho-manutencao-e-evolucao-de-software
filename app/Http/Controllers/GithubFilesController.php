<?php

namespace SegWeb\Http\Controllers;

use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use SegWeb\File;
use SegWeb\FileResults;
use SegWeb\Services\UserService;
use SegWeb\Services\FileService;
use SegWeb\Http\Controllers\Tools;
use SegWeb\Http\Controllers\FileController;
use SegWeb\Http\Controllers\FileResultsController;

class GithubFilesController extends Controller
{
    private $github_files_ids = NULL;

    public function __construct(protected FileService $fileService, protected UserService $userService) {}

    public function index()
    {
        return view('github');
    }

    private function createResponse($path, $viewResponse, $apiResponse)
    {
        if ($path == "github") {
            return response()->view('github', $viewResponse);
        } else {
            return response()->json($apiResponse)->header('Content-Type', "json");
        }
    }

    public function downloadGithub(Request $request)
    {
        if (!Tools::contains("github", $request->github_link)) {
            $msg['text'] = "An invalid repository link has been submitted!";
            $msg['type'] = "error";

            return $this->createResponse($request->path(), compact(['msg']), ['error' => $msg['text']]);
        }

        $msg = ['text' => 'Repository has been successfully downloaded!', 'type' => 'success'];
        try {
            $user_id = $this->userService->getUser();

            // Baixa o arquivo .zip do github
            $github_link = substr($request->github_link, -1) == '/' ? substr_replace($request->github_link, "", -1)  : $request->github_link;

            $url = $github_link . '/archive/' . $request->branch . '.zip';
            $folder = 'github_uploads/';
            $now = date('ymdhis');
            $name = $folder . $now . '_' . substr($url, strrpos($url, '/') + 1);
            $put = Storage::put($name, file_get_contents($url));

            if (!$put) {
                $msg['text'] = "An error occurred during repository download";
                $msg['type'] = "error";

                throw new \Exception("An error occurred during repository download", 999);
            }

            // Extrai e exclui o arquivo .zip do github
            $file_location = base_path('storage/app/' . $folder . $now . '_' . $request->branch);

            $zip = new \ZipArchive();
            if ($zip->open(base_path('storage/app/' . $name), \ZipArchive::CREATE) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $zip->extractTo($file_location, array($zip->getNameIndex($i)));
                }
                $zip->close();
            }

            unlink(base_path('storage/app/' . $name));

            // Salva o registro do repositório do github
            $project_name = explode('/', $github_link);
            $project_name = $project_name[sizeof($project_name) - 1];

            $file = $this->fileService->saveFile($user_id, $folder . $now . '_' . $request->branch, $project_name, "Github Repository");

            // Realiza a análise dos arquivos do repositório
            $this->analiseGithubFiles($file_location, $file->id);

            // Busca o conteúdo dos arquivos para exibição
            $file_results_controller = new FileResultsController();
            $file_contents = NULL;
            if (!empty($this->github_files_ids)) {
                foreach ($this->github_files_ids as $value) {
                    $file_contents[$value]['content'] = FileController::getFileContentArray($value);
                    $file_contents[$value]['results'] = $file_results_controller->getSingleByFileId($value);
                    $file_contents[$value]['file'] = FileController::getFileById($value);
                }
            }

            return $this->createResponse($request->path(), compact(['file', 'file_contents', 'msg']), $this->getResultArray($file, $file_contents));
        } catch (\Exception $e) {
            $msg['text'] = $e->getCode() === 999 ? $e->getMessage() : "An error occurred";
            $msg['type'] = "error";

            return $this->createResponse($request->path(), compact(['msg']), ['error' => $msg['text']]);
        }
    }

    public function analiseGithubFiles($dir, $repository_id)
    {
        $ffs = scandir($dir);
        unset($ffs[array_search('.', $ffs, true)]);
        unset($ffs[array_search('..', $ffs, true)]);

        if (empty($ffs)) {
            return;
        }

        $term = new TermController();
        $terms = $term->getTerm();
        foreach ($ffs as $ff) {
            $full_file_path = $dir . "/" . $ff;
            $file_path = explode("storage/app/", $full_file_path)[1];
            if (is_dir($full_file_path)) {
                $this->analiseGithubFiles($full_file_path, $repository_id);
            } else {
                if (mime_content_type($full_file_path) == "text/x-php" || mime_content_type($full_file_path) == "application/x-php") {
                    $user_id = $this->userService->getUser();

                    $file = $this->fileService->saveFile($user_id, $file_path, $ff, "Github File", $repository_id);

                    $this->github_files_ids[] = $file->id;

                    $fn = fopen($full_file_path, 'r');
                    $line_number = 1;
                    while (!feof($fn)) {
                        $file_line = fgets($fn);

                        $this->saveFileResultsByLine($terms, $file, $file_line, $line_number);

                        $line_number++;
                    }
                    fclose($fn);
                }
            }
        }
    }

    private function saveFileResultsByLine($terms, $file, $file_line, $line_number)
    {
        foreach ($terms as $term) {
            if (Tools::contains($term->term, $file_line)) {
                $file_results = new FileResults();
                $file_results->file_id = $file->id;
                $file_results->line_number = $line_number;
                $file_results->term_id = $term->id;
                $file_results->save();
            }
        }
    }

    public function getResultArray($file, $file_contents)
    {
        $array = [];
        foreach ($file_contents as $value) {
            $file_results = $value['results'];
            $file_path = explode('/', explode($file->original_file_name, $value['file']->file_path)[1]);
            unset($file_path[0]);
            $file_path = $file->original_file_name . '/' . implode('/', $file_path);

            $array[] = ['file' => $file_path];

            foreach ($file_results as $results) {
                $array['problems'][] = [
                    'line' => $results->line_number,
                    'category' => $results->term_type,
                    'problem' => $results->term
                ];
            }
        }
        return $array;
    }
}
