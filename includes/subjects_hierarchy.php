<?php
$subjects_hierarchy = [
    'Mathematics' => [
        'Algebra',
        'Calculus', 
        'Geometry',
        'Trigonometry',
        'Statistics',
        'Probability',
        'Linear Algebra',
        'Differential Equations'
    ],
    'Science' => [
        'General Science',
        'Earth Science', 
        'Environmental Science',
        'Scientific Method',
        'Laboratory Techniques'
    ],
    'Physics' => [
        'Mechanics',
        'Thermodynamics',
        'Electromagnetism', 
        'Optics',
        'Modern Physics',
        'Quantum Physics',
        'Nuclear Physics'
    ],
    'Chemistry' => [
        'General Chemistry',
        'Organic Chemistry',
        'Inorganic Chemistry',
        'Physical Chemistry', 
        'Analytical Chemistry',
        'Biochemistry'
    ],
    'Biology' => [
        'Cell Biology',
        'Genetics',
        'Ecology',
        'Human Biology',
        'Botany',
        'Zoology',
        'Microbiology',
        'Evolution'
    ],
    'English' => [
        'Grammar',
        'Literature', 
        'Creative Writing',
        'Essay Writing',
        'Reading Comprehension',
        'Public Speaking',
        'Poetry'
    ],
    'Filipino' => [
        'Gramatika',
        'Panitikan',
        'Pagbasa at Pag-unawa',
        'Pagsulat',
        'Wika at Kultura'
    ],
    'History' => [
        'World History',
        'Philippine History',
        'Ancient History',
        'Modern History',
        'Historical Research'
    ],
    'Geography' => [
        'Physical Geography',
        'Human Geography',
        'World Geography',
        'Philippine Geography',
        'Cartography'
    ],
    'Computer Science' => [
        'Programming Fundamentals',
        'Data Structures',
        'Algorithms',
        'Database Systems',
        'Web Development',
        'Software Engineering',
        'Computer Networks'
    ],
    'Programming' => [
        'Python',
        'Java',
        'JavaScript',
        'C++',
        'HTML/CSS',
        'PHP',
        'Mobile Development'
    ],
    'Accounting' => [
        'Financial Accounting',
        'Management Accounting',
        'Cost Accounting',
        'Auditing',
        'Taxation'
    ],
    'Economics' => [
        'Microeconomics',
        'Macroeconomics',
        'International Economics',
        'Development Economics',
        'Economic Theory'
    ],
    'Psychology' => [
        'General Psychology',
        'Developmental Psychology',
        'Social Psychology',
        'Cognitive Psychology',
        'Abnormal Psychology'
    ],
    'Sociology' => [
        'Social Theory',
        'Social Research',
        'Cultural Sociology',
        'Urban Sociology',
        'Family Studies'
    ],
    'Philosophy' => [
        'Ethics',
        'Logic',
        'Metaphysics',
        'Political Philosophy',
        'Philosophy of Mind'
    ],
    'Art' => [
        'Drawing',
        'Painting',
        'Sculpture',
        'Digital Art',
        'Art History',
        'Graphic Design'
    ],
    'Music' => [
        'Music Theory',
        'Piano',
        'Guitar',
        'Voice',
        'Music History',
        'Composition'
    ],
    'Physical Education' => [
        'Sports',
        'Fitness',
        'Health Education',
        'Recreation',
        'Athletic Training'
    ],
    'Research' => [
        'Research Methods',
        'Data Analysis',
        'Academic Writing',
        'Literature Review',
        'Statistical Analysis'
    ]
];

// Function to get the complete subjects hierarchy
function getSubjectsHierarchy() {
    global $subjects_hierarchy;
    return $subjects_hierarchy;
}

// Function to get subjects as JSON for JavaScript
function getSubjectsHierarchyJSON() {
    global $subjects_hierarchy;
    return json_encode($subjects_hierarchy);
}

// Function to get all main subjects
function getMainSubjects() {
    global $subjects_hierarchy;
    return array_keys($subjects_hierarchy);
}

// Function to get subtopics for a main subject
function getSubtopics($main_subject) {
    global $subjects_hierarchy;
    return isset($subjects_hierarchy[$main_subject]) ? $subjects_hierarchy[$main_subject] : [];
}

// Function to find which main subject a subtopic belongs to
function findMainSubjectForSubtopic($subtopic) {
    global $subjects_hierarchy;
    foreach ($subjects_hierarchy as $main => $subtopics) {
        if (in_array($subtopic, $subtopics)) {
            return $main;
        }
    }
    return null;
}
?>
