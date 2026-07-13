# Use cases — Announcements & Notifications

Personas: **All** (receive), **Instructor/Service Admin** (announce), **SuperAdmin**. Controllers:
`AnnouncementController`, `AnnouncementManageController`, `NotificationController`,
`NotificationSettingsController`, `StudentBirthdaysController`; services `AnnouncementService`,
`NotificationDispatchService`, `NotificationGeneratorService`, `NotificationFeedService`,
`NotificationPreferenceService`, `NotificationScannerService`, `WhatsAppNotificationService`,
`BirthdayNotificationService`.

| UC | Persona | Main path | Alternate / error paths | Authorization boundary |
|---|---|---|---|---|
| UC-ANN-01 | Instructor/Service Admin | Create → publish an announcement to a target audience (course/service) | Draft vs publish; revision history kept | `announcement.manage` + `announcement.publish` |
| UC-ANN-02 | Member | View announcements; dismiss banner | Mandatory announcement can't be dismissed | `announcement.view` |
| UC-ANN-03 | Manager | Mark WhatsApp delivery / send via WhatsApp | Not configured → delivery `failed` gracefully | `announcements.manage.whatsapp` |
| UC-NOT-01 | Any user | Receive in-portal notification for a domain event (role assigned, session soon, exam upcoming, birthday…) | Respects channel preferences | recipient-scoped |
| UC-NOT-02 | Any user | View notification feed; open a notification (marks read, follows action link) | Cannot open another user's notification (403) | own notifications |
| UC-NOT-03 | Any user | Mark all read → unread badge cleared | — | own |
| UC-NOT-04 | Any user | Manage notification preferences (portal/email/WhatsApp) & reminders | Mandatory categories can't be fully disabled | own preferences |
| UC-NOT-05 | System | Email + WhatsApp dispatched per preference; external HTTP faked in tests | External failure logged, delivery recorded | server |

**Coverage:** `NotificationHubTest`, `NotificationActionLinkTest`, `SessionUpcomingNotificationTest`,
`StudentBirthdayAnnouncementTest`, `AnnouncementModuleTest`, `RoleAssignmentNotificationTest`,
`UseCases/../NotificationDeliveryTest`, `Mail/ExternalCommunicationTest` (email + WhatsApp).
