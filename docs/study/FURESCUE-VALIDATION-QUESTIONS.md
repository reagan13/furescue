# FuRescue validation questionnaire

The original Word file is `FURESCUE-QUESTIONNAIRE.docx` in this folder (copied from the survey the researchers will hand out). This page is the same statement list with stable IDs so web and mobile work can be checked against it.

The survey is a second checklist beside `OBJECTIVES.md` and `REQUIREMENTS_SPECIFICATION.md`. A flow that meets an objective still leaves a gap when the matching statement cannot be done by that role. Open gaps are listed in `VALIDATION-GAPS.md`.

Three forms. Scale on every form: 5 Strongly Agree, 4 Agree, 3 Fairly Agree, 2 Disagree, 1 Strongly Disagree, plus a comments box.

| Form | Who fills it | Where the product must answer |
|------|----------------|--------------------------------|
| User | Community member (reporter / adopter) | Resident web and the resident phone app |
| Responder | Rescuer | Rescuer web and the rescuer phone app |
| Administrator | City vet / admin | Admin web only. The phone app does not build admin screens. |

Wording below is the printed form, including its typos and repeated numbers. Do not silently rewrite a statement when scoring a gap. Notes under a row are for the team, not a change to the instrument.

## Instrument problems to fix before respondents see the form

These are copy-paste errors in the Word file. They are not FuRescue features.

- The title says “Cross-Platfrom”.
- The consent contact line still says `[contact number]` and `[group email address` (the bracket is unclosed).
- The user form numbers two statements `8` and two statements `20`.
- User reliability 18 and security 19 say “rice field information”.
- Administrator usability 18 says “the AgriVista system”.
- The responder compatibility line says “mobile web application” on Android. The study’s phone client is the Flutter app; the website is the responsive web app.

## User form

### Functional suitability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-FS-1 | 1 | I can easily view the animals details and availability for adoption using the system. |
| U-FS-2 | 2 | I can easily submit an adoption request through the system. |
| U-FS-3 | 3 | I can easily report stray, injured, or abandoned animals including the geotagging feature through the system. |
| U-FS-4 | 4 | I can receive notifications about my adoption request and reports. |
| U-FS-5 | 5 | I can use the messaging feature to communicate with the responders/administrators. |
| U-FS-6 | 6 | I can clearly view the 3D profile of an animal. |
| U-FS-7 | 7 | I can access the E-Learning feature easily. |

### Performance efficiency

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-PE-1 | 8 | The system responds quickly when I submit a report or adoption request. |
| U-PE-2 | 8 | The geotagging feature and animal information loads within a reasonable time |
| U-PE-3 | 9 | I receive the notifications and updates within a reasonable time |

### Compatibility

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-CO-1 | 10 | The FURescue website works properly on my device |
| U-CO-2 | 11 | The system works well with different internet connections, including Wi-Fi and ,mobile data. |

### Usability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-US-1 | 12 | I can easily recognize the purpose and functions of the FURescue system. |
| U-US-2 | 13 | The system is easy to learn for first-time users. |
| U-US-3 | 14 | The buttons, menus, maps, and other controls are intuitive and easy to use |
| U-US-4 | 15 | The system provides appropriate messages or warnings when errors occur. |
| U-US-5 | 16 | The interface is visually appealing and well-designed. |

### Reliability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-RE-1 | 17 | The system consistently operates without frequent technical problems or interruptions. |
| U-RE-2 | 18 | The system reliably displays and saves rice field information. |

Team reading of U-RE-2 until the form is corrected: the system reliably displays and saves the user’s report, adoption, and animal information. Do not build a rice-field feature.

### Security

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-SE-1 | 19 | My personal information and rice field information are protected from unauthorized access. |
| U-SE-2 | 20 | The system provides proper login and access controls for authorized users. |

Team reading of U-SE-1 until the form is corrected: personal information and the user’s animal-welfare records are protected from unauthorized access.

### Portability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| U-PO-1 | 20 | The FURescue web application can be accessed using different devices. |
| U-PO-2 | 21 | The system is easily accessible on my mobile device. |

## Responder form

### Functional suitability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-FS-1 | 1 | I can easily view and manage animal reports that are assigned to me. |
| R-FS-2 | 2 | I can easily view the location of reported animals using the map |
| R-FS-3 | 3 | I can easily update the status of a rescue case. |
| R-FS-4 | 4 | I can view the animal’s information and health records. |
| R-FS-5 | 5 | I can use the messaging feature to communicate with users and administrators |
| R-FS-6 | 6 | I can easily update the health and vaccination information of rescued animals |

### Performance efficiency

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-PE-1 | 7 | The system responds quickly when viewing or updating rescue cases. |
| R-PE-2 | 8 | The map displays reported animal locations within a reasonable time |
| R-PE-3 | 9 | I receive notifications and case updates within a reasonable time. |

### Compatibility

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-CO-1 | 10 | The mobile web application is compatible with my Android device. |
| R-CO-2 | 11 | The system works well with different internet connections, including Wi-Fi and mobile data. |

### Usability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-US-1 | 12 | I can easily understand the features and functions of FURescue. |
| R-US-2 | 13 | The system is easy to learn and use for rescue activities. |
| R-US-3 | 14 | The buttons, menus, maps, and other controls are intuitive and easy to use. |
| R-US-4 | 15 | The system provides appropriate messages or warnings when errors occur. |
| R-US-5 | 16 | The interface is organized and easy to navigate |

### Reliability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-RE-1 | 17 | The system consistently operates without frequent technical problems or interruptions. |
| R-RE-2 | 18 | The system reliably saves and displays rescue case information |

### Security

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-SE-1 | 19 | Animal and user information is protected from unauthorized access |
| R-SE-2 | 20 | The system provides proper login and access controls for authorized users. |

### Portability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| R-PO-1 | 21 | The FURescue web application can be accessed using different devices. |
| R-PO-2 | 22 | The system is easy to access and use during rescue activities. |

## Administrator form

### Functional suitability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-FS-1 | 1 | I can easily add, update, and manage animal records. |
| A-FS-2 | 2 | I can easily manage reported strays, injured, and abandoned animals |
| A-FS-3 | 3 | I can easily manage adoption request |
| A-FS-4 | 4 | I can easily verify and update adoption requests. |
| A-FS-5 | 5 | I can easily manage animal health and vaccine records. |
| A-FS-6 | 6 | I can easily view statistics about the geographic distribution of reported animal case using the Geotagging Feature |
| A-FS-7 | 7 | I can easily generate reports and statistics about adoption trends and rescue activities. |
| A-FS-8 | 8 | I can manage notifications and updates related to reports and adoption requests. |
| A-FS-9 | 9 | I can manage animal information used in the adoption portal. |
| A-FS-10 | 10 | I can manage educational content for the E-learning feature. |
| A-FS-11 | 11 | I can manage animal profiles and 3D information. |
| A-FS-12 | 12 | I can use the messaging feature to communicate with the adopters and responders. |

### Performance efficiency

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-PE-1 | 13 | The system responds quickly when I add, update, search, or manage animal records. |
| A-PE-2 | 14 | The system generates reports and statistics within a reasonable time. |
| A-PE-3 | 15 | The system displays reported cases and map information within a reasonable time. |

### Compatibility

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-CO-1 | 16 | The FURescue web application works properly on different devices and web browsers. |
| A-CO-2 | 17 | The system works properly with different internet connections, including Wi-Fi and mobile data. |

### Usability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-US-1 | 18 | I can easily recognize the purpose and functions of the AgriVista system. |
| A-US-2 | 19 | The system is easy to learn and use for administrative tasks. |
| A-US-3 | 20 | The buttons, menus, forms, dashboard, and other controls are easy to use. |
| A-US-4 | 21 | The system provides clear message or warnings when error occurs |
| A-US-5 | 22 | The interface is done proper and is efficient to navigate |

Team reading of A-US-1 until the form is corrected: the administrator can recognize the purpose and functions of FuRescue.

### Reliability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-RE-1 | 23 | The system operates without frequent technical problems or interruptions. |
| A-RE-2 | 24 | The system reliably stores, retrieves, and displays animal records. |

### Security

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-SE-1 | 25 | Animal records and user information are protected from unauthorized access. |
| A-SE-2 | 26 | The system provides proper login and access controls for administrator functions. |

### Maintainability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-MN-1 | 27 | The system can be tested to identify technical issues and errors. |
| A-MN-2 | 28 | The system can be updated or modified when new animal welfare requirements appear. |

### Portability

| ID | Printed no. | Statement |
|----|-------------|-----------|
| A-PO-1 | 29 | FURescue web application can be accessed using different devices. |
| A-PO-2 | 30 | The system can be easily accessed and used in compatible web browsers and android devices. |

## What the study requires that this survey does not ask

Keep these in the gap list even though no statement above names them. Source: `OBJECTIVES.md` and `REQUIREMENTS_SPECIFICATION.md`.

- Objective 7: community members post animals for adoption (listings).
- Objective 10 / health monitoring: medical history, vaccination, and heart rate. The responder form asks for health and vaccination updates. Heart rate is only in the objectives.
- Community requirement: upload photos or videos on a report. The user form does not say “video”.
- System requirements: geotag mapping, lifecycle Reported → Rescued → Under Care → Adopted, statistics for adoption trends and rescue rates, real-time alerts.
- Scope: dogs and cats only (Aspin and Puspin), Mati City only, role-based access, responsive web and a mobile client, no payments, no Messenger/WhatsApp, no AI recognition.
