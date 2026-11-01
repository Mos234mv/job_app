<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyJobRequest;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Resume;
use App\Services\ResumeAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobVacancyController extends Controller
{
    protected $resumeAnalysisService;

    public function __construct(ResumeAnalysisService $resumeAnalysisService)
    {
        $this->resumeAnalysisService = $resumeAnalysisService;
    }

    public function show(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);

        return view('job-vacancies.show', compact('jobVacancy'));
    }

    public function apply(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $resumes = Auth::user()->resumes;

        return view('job-vacancies.apply', compact('jobVacancy', 'resumes'));
    }

    public function processApplication(ApplyJobRequest $request, string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $resumeId = null;
        $extractedInfo = null;

        if ($request->input('resume_option') === 'new_resume') {
            $file = $request->file('resume_file');
            $extension = $file->getClientOriginalExtension();
            $originalFileName = $file->getClientOriginalName();
            $fileName = 'resume_'.time().'.'.$extension;

            // Store in laravel cloud
            $path = $file->storeAs('resumes', $fileName, [
                'disk' => 'cloud',
                'visibility' => 'public',
            ]);

            $fileUrl = config('filesystems.disks.cloud.url').'/'.$path;

            $extractedInfo = $this->resumeAnalysisService->extractResumeInformation($fileUrl);

            $resume = Resume::create([
                'fileName' => $originalFileName,
                'fileUrl' => $path,
                'user_id' => Auth::id(),
                'contactDetails' => json_encode([
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                ]),
                'summary' => $extractedInfo['summary'],
                'skills' => json_encode($extractedInfo['skills']),
                'experience' => json_encode($extractedInfo['experience']),
                'education' => json_encode($extractedInfo['education']),
            ]);

            $resumeId = $resume->id;

        } else {
            $resumeId = $request->input('resume_option');
            $resume = Resume::findOrFail($resumeId);

            $extractedInfo = [
                'summary' => $resume->summary,
                'skills' => $resume->skills,
                'experience' => $resume->experience,
                'education' => $resume->education,
            ];
        }

        // Evalute Job Application
        $evaluation = $this->resumeAnalysisService->analyzeResume($jobVacancy, $extractedInfo);

        JobApplication::create([
            'status' => 'pending',
            'job_vacancy_id' => $id,
            'resume_id' => $resumeId,
            'user_id' => Auth::id(),
            'aiGeneratedScore' => $evaluation['aiGeneratedScore'],
            'aiGeneratedFeedback' => $evaluation['aiGeneratedFeedback'],
        ]);

        return redirect()->route('job-applications.index', $id)->with('success', 'Application submitted successfully');
    }

public function store (){

}
   
}
