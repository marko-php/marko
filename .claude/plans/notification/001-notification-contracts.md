# Task 001: Notification Contracts

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the core interfaces for the notification system: NotificationInterface, NotifiableInterface, ChannelInterface, and the exception hierarchy. These contracts define how notifications are built, who can receive them, and how channels deliver them.

## Context
- Namespace: `Marko\Notification\`
- Package: `marko/notification`
- Dependencies: marko/core (for MarkoException)
- Pattern: Same contract-first approach as marko/mail (MailerInterface), marko/queue (QueueInterface)
- Reference: packages/mail/src/Contracts/ and packages/queue/src/

### Interface Locations
- `src/Contracts/NotificationInterface.php`
- `src/Contracts/NotifiableInterface.php`
- `src/Contracts/ChannelInterface.php`
- `src/Exceptions/NotificationException.php`
- `src/Exceptions/ChannelException.php`

### Key Design Decisions
- `NotificationInterface::channels()` receives the notifiable so channels can vary per recipient
- `toMail()` returns `Marko\Mail\Message` -- but this is an optional dependency (not required at interface level)
- `toDatabase()` returns `array<string, mixed>` for flexible JSON storage
- `NotifiableInterface::routeNotificationFor()` returns `mixed` since each channel expects different routing (email string, ID, etc.)
- `NotifiableInterface` includes `getNotifiableId()` and `getNotifiableType()` for database notification storage
- Exceptions follow the three-part pattern (message, context, suggestion)

## Requirements (Test Descriptions)
- [ ] `it defines NotificationInterface with channels, toMail, and toDatabase methods`
- [ ] `it defines NotifiableInterface with routeNotificationFor, getNotifiableId, and getNotifiableType methods`
- [ ] `it defines ChannelInterface with send method accepting notifiable and notification`
- [ ] `it defines NotificationException extending MarkoException with factory methods`
- [ ] `it defines ChannelException extending NotificationException with routeMissing and deliveryFailed factories`
- [ ] `it creates NotificationException with context and suggestion via unknownChannel factory`
- [ ] `it creates ChannelException with context and suggestion via routeMissing factory`

## Acceptance Criteria
- All three interfaces exist with proper method signatures and type declarations
- NotificationException provides static factory methods: unknownChannel, noQueueAvailable, sendFailed
- ChannelException provides static factory methods: routeMissing, deliveryFailed
- All exceptions include message, context, and suggestion
- Strict types declared, no final classes, constructor property promotion where applicable
- PHPDoc with @throws tags on ChannelInterface::send()

## Implementation Notes
(Left blank)
