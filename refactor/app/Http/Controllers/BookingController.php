<?php

namespace refactor\App\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use http\Env\Response;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(BookingRequest $request)
    {
        try {
            ## BookingRequest Injection will validate all request's data, so no anyone inject dirty codes, character and invalid data to database.

            ## response variable is not defined if both conditions get false.
            $response = null;

            if ($user_id = $request->get('user_id')) {
                $response = $this->repository->getUsersJobs($user_id);
            } elseif ($request->__authenticatedUser->user_type == env('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == env('SUPERADMIN_ROLE_ID')) {
                ## I believe custom checks are not good to checkout the permission, you should use Gates and permission at ServiceProvider file.
                $response = $this->repository->getAll($request);
            }

            return response()->json([
                'status' => true,
                ## Use laravel resource to parse the API data
                'results' => new BookingResource($response)
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                ## Use laravel resource to parse the API data
                'results' => new \stdClass()
            ]);
        }

    }

    /**
     * @param $id
     * @return mixed
     */
    public function show(int $id)
    {
        ## typecast id variable to int so, no one can pass string and other data to query parameter and that data would not go to database query
        try {

            ## prefer to use eloquents, for fetching records from database, it contains MVC structure.
            $job = Jobs::getJobById($id);
            $message = ($job instanceof Job) ? trans('job.found') : trans('job.not.found');
            return response()->json(['status' => true, 'job' => $job, 'message' => $message]);

        } catch (\Exception $exception) {

            return response()->json(['status' => false, 'job' => new \stdClass(), 'message' => $exception->getMessage()]);

        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(SaveNewBooking $request)
    {
        ## SaveNewBooking is request validation file, which check the authenticate user, and the data validity.
        try {
            $BookingResponse = BookingRepository::saveNewBooking($request->all());
            $message = ($BookingResponse instanceof BookingRepository) ? trans('new.booking.saved') : trans('failed.to.add.new.booking');
            return response()->json(['status' => true, 'booking' => new BookingResource($BookingResponse), 'message' => $message]);
        } catch (\Exception $exception) {
            return response()->json(['status' => false, 'booking' => new \stdClass(), 'message' => $exception->getMessage()]);
        }


    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update(int $id, UpdateBookingRequest $request)
    {
        ## UpdateBookingRequest is request validation file, which check the authenticate user, and the data validity.
        try {
            $BookingResponse = BookingRepository::updateBooking($request->all(), $id);
            $message = ($BookingResponse instanceof BookingRepository) ? trans('booking.has.been.updated') : trans('failed.to.update.booking');
            return response()->json(['status' => true, 'booking' => new BookingResource($BookingResponse), 'message' => $message]);
        } catch (\Exception $exception) {
            return response()->json(['status' => false, 'booking' => new \stdClass(), 'message' => $exception->getMessage()]);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(GetHistoryRequest $request)
    {
        ## This request check user's is authorized and has a enoughf rights to do access this data or not so, no need if, else check in the controller.
        try {
            $History = History::getHistoryBYLoggedInUser();
            return response()->json(['status' => true, 'message' => trans('history.found'), 'history' => new \stdClass()]);
        } catch (\Exception $exception) {
            return response()->json(['status' => false, 'message' => $exception->getMessage(), 'history' => new \stdClass()]);
        }
    }

    public function distanceFeed(DistanceFeedRequest $request)
    {
        ## use all validation to distance feed request

        try {
            if ($request->time || $request->distance) {
                $affectedRows = Distance::updateViaTime(['distance' => $request->distance, 'time' => $request->time], $request->jobId);
            }
            if ($request->admincomment || $request->session || $request->flagged || $request->manually_handled || $request->by_admin) {
                $affectedRows1 = Job::update(['admin_comments' => $request->admincomment, 'flagged' => $request->flagged, 'session_time' => $request->session, 'manually_handled' => $request->manually_handled, 'by_admin' => $request->by_admin]);
            }
            return response()->json(['status' => true, 'message' => trans('updated')]);
        } catch (\Exception $exception) {
            return response()->json(['status' => false, 'message' => $exception->getMessage()]);

        }
    }

    public function resendNotifications(Request $request)
    {
        $request->user()->notify(new sendNotificationTranslator($request));
        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $request->user()->notify(new sendNotificationTranslator($request));
        return response(['success' => 'Push sent']);
    }

}
