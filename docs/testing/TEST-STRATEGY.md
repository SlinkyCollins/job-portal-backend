# JobNet Test Strategy v1

## Purpose

The purpose of the test suite is migration safety.

The tests protect important existing JobNet behavior so that the PHP → Laravel
and Angular modernization can be performed without silently changing behavior.

This is not a goal of achieving high test coverage.

## Testing principle

> What level of test best protects the behavior I'm changing?

## Test levels

### Integration
Used primarily for backend/API workflows.

Tests interact with the API through HTTP and use the testing database.

### Unit
Used selectively for isolated business logic where integration testing would
be unnecessarily expensive or complicated.

### E2E
Used selectively for critical browser-level workflows where frontend/backend
interaction itself needs protection.

## Critical workflows

1. Native signup
2. Native login
3. Firebase/Google login
4. Firebase/Facebook login
5. Social account linking
6. Authentication/session/token handling
7. Role authorization / protected endpoints
8. Job creation
9. Job editing
10. Job listing/search/filtering
11. Job details
12. Job application
13. Application ownership / employer access control
14. Resume upload
15. Admin authorization
16. Admin user management
17. User deletion / associated-data cleanup

## Important workflows

18. Saved jobs / wishlist
19. Employer dashboard data
20. Seeker dashboard data
21. Admin dashboard statistics
22. Company profile management
23. Profile/account settings
24. Cloudinary uploads/deletions
25. Salary normalization/conversion
26. Pagination/sorting/filtering
27. Job application status changes

## Useful workflows

28. Loading states
29. Empty states
30. Error states
31. Form validation
32. Toast/notification behavior
33. UI component behavior
34. Dashboard rendering

## Low priority workflows

35. Exact styling
36. Pixel-level visual appearance
37. Animation
38. Responsive layout details