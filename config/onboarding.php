<?php

/*
 * Therapist onboarding (2026-11-03). Everything the application asks, in one place, so
 * Dr. Ntabade can change a list or a wording without touching the screens.
 *
 * Changing a consent's text? Raise its `version`. Everyone who agreed to the old version
 * keeps that record; new applicants agree to the new one. See App\Models\ConsentRecord.
 */

return [

    'steps' => [
        'licence' => 'Licence',
        'training' => 'Training',
        'fitness' => 'Fitness to practise',
        'profile' => 'Profile',
        'consents' => 'Consents',
        'review' => 'Review',
    ],

    'professions' => [
        'counselling_psychologist' => 'Counselling psychologist',
        'clinical_psychologist' => 'Clinical psychologist',
        'counsellor' => 'Counsellor',
        'psychiatrist' => 'Psychiatrist',
        'mental_health_nurse' => 'Psychiatric / mental health nurse',
        'social_worker' => 'Clinical social worker',
        'other' => 'Other',
    ],

    // DRAFT for Dr. Ntabade to confirm (2026-11-03): which body registers which
    // profession in Uganda could not be fully confirmed online.
    'regulators' => [
        'ahpc' => 'Allied Health Professionals Council (AHPC)',
        'umdpc' => 'Uganda Medical and Dental Practitioners Council',
        'unmc' => 'Uganda Nurses and Midwives Council',
        'uca' => 'Uganda Counselling Association (membership)',
        'upa' => 'Uganda Psychological Association (membership)',
        'other' => 'Other',
    ],

    'qualification_levels' => [
        'diploma' => 'Diploma',
        'bachelors' => "Bachelor's degree",
        'postgrad_diploma' => 'Postgraduate diploma',
        'masters' => "Master's degree",
        'doctorate' => 'Doctorate (PhD, PsyD)',
        'medical' => 'Medical degree with psychiatry specialisation',
    ],

    'specialties' => [
        'Anxiety', 'Depression', 'Stress and burnout', 'Trauma and PTSD', 'Grief and loss',
        'Relationships and couples', 'Family conflict', 'Parenting', 'Addiction and substance use',
        'Self-esteem', 'Anger', 'Sleep', 'Work and career', 'Gender-based violence',
        'Bipolar disorder', 'Psychosis', 'Eating difficulties', 'LGBTQ+ affirming care',
    ],

    'approaches' => [
        'Cognitive behavioural therapy (CBT)', 'Person-centred therapy', 'Psychodynamic therapy',
        'Solution-focused brief therapy', 'Family systems therapy', 'Trauma-focused therapy (TF-CBT, EMDR)',
        'Acceptance and commitment therapy (ACT)', 'Dialectical behaviour therapy (DBT)',
        'Mindfulness-based therapy', 'Motivational interviewing', 'Narrative therapy', 'Interpersonal therapy (IPT)',
    ],

    'languages' => [
        'English', 'Luganda', 'Swahili', 'Runyankole / Rukiga', 'Lusoga', 'Acholi / Luo', 'Ateso',
        'Lugbara', 'Rutooro / Runyoro', 'Lumasaba', 'Kinyarwanda', 'French',
    ],

    'age_groups' => [
        'children' => 'Children (under 13)',
        'teens' => 'Teenagers (13 to 17)',
        'adults' => 'Adults',
        'older' => 'Older adults (65+)',
        'couples' => 'Couples',
        'families' => 'Families',
    ],

    'session_types' => [
        'video' => 'Video sessions',
        'in_person' => 'In person (Mindwell venues)',
    ],

    // Fitness to practise. Standard questions used by health regulators and telehealth
    // services. A "yes" always needs details; it is a flag for review, not an automatic no.
    'declarations' => [
        'licence_action' => 'Has any licence or registration you hold ever been suspended, revoked, restricted, or not renewed?',
        'disciplinary' => 'Have you ever been subject to disciplinary action by a regulator, professional body or employer?',
        'pending' => 'Is any complaint, investigation or disciplinary process against you currently open?',
        'criminal' => 'Have you ever been convicted of a criminal offence, or are any criminal charges pending against you? (Minor traffic offences excluded.)',
        'safeguarding' => 'Have you ever been found to have harmed, abused or neglected a child or vulnerable adult?',
        'health' => 'Do you have any health condition, including substance use, that currently affects your ability to practise safely?',
    ],

    'documents' => [
        'licence' => ['label' => 'Licence or registration certificate', 'required' => true, 'step' => 'licence'],
        'national_id' => ['label' => 'National ID or passport', 'required' => false, 'step' => 'licence'],
        'cv' => ['label' => 'CV', 'required' => true, 'step' => 'training'],
        'insurance' => ['label' => 'Indemnity insurance certificate', 'required' => false, 'step' => 'fitness'],
    ],

    'upload' => [
        'mimes' => 'pdf,jpg,jpeg,png',
        'max_kb' => 5120,
    ],

    // Recorded consents. DRAFT wording for Dr. Ntabade to review.
    'consents' => [
        'code_of_conduct' => [
            'title' => 'Code of conduct',
            'version' => '2026-11',
            'points' => [
                'I will practise only within my training, competence and the terms of my licence or registration.',
                'I will treat every client with dignity and respect, without discrimination of any kind.',
                'I will keep clear professional boundaries. I will not enter any personal, financial or sexual relationship with a client.',
                'I will follow Mindwell\'s crisis procedure whenever a client may be at risk, and tell the Mindwell team straight away.',
                'I will keep my licence current and tell Mindwell within 7 days if it is suspended, restricted or not renewed, or if any complaint is made against me.',
                'I will write and close session reports on time.',
            ],
        ],
        'confidentiality' => [
            'title' => 'Confidentiality',
            'version' => '2026-11',
            'points' => [
                'Everything a client shares with me stays confidential, inside and outside Mindwell.',
                'I will only break confidentiality when the law requires it or someone is at serious risk of harm, and I will follow Mindwell\'s procedure when I do.',
                'I will hold video sessions in a private place where no one can see or hear the client.',
                'I will not record sessions, take screenshots, or copy client information out of Mindwell.',
                'This duty continues after I stop working with Mindwell.',
            ],
        ],
        'data_protection' => [
            'title' => 'Data protection',
            'version' => '2026-11',
            'points' => [
                'I will handle client personal data in line with Uganda\'s Data Protection and Privacy Act, 2019, and Mindwell\'s privacy policy.',
                'I will only open the records I need for my own clients\' care.',
                'I will keep my Mindwell password private and lock or log out of shared devices.',
                'I will tell Mindwell at once if I think client information has been seen by someone who shouldn\'t have it.',
                'I agree that Mindwell may store and check the details and documents in this application, including contacting my regulator and my referees.',
            ],
        ],
    ],

    // Admin verification checklist. `required` items must be ticked before approving.
    'checklist' => [
        'licence_verified' => ['label' => 'Licence confirmed with the regulator or professional body', 'required' => true],
        'licence_in_date' => ['label' => 'Licence is in date', 'required' => true],
        'cv_reviewed' => ['label' => 'CV and qualifications reviewed', 'required' => true],
        'declarations_reviewed' => ['label' => 'Fitness to practise answers reviewed', 'required' => true],
        'references_contacted' => ['label' => 'Both references contacted', 'required' => false],
        'id_checked' => ['label' => 'ID checked against the name on the licence', 'required' => false],
        'interview_held' => ['label' => 'Interview held', 'required' => false],
    ],

];
