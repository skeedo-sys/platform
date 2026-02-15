<?php

namespace Assistant\Domain;

enum Scope: string
{
    // Only assistants created by the user
    case PRIVATE = 'private';

        // Only assistants created by the team members but not user itself
    case TEAM = 'team';

        // Only assistants created by the community
    case COMMUNITY = 'community';

        // Only assistants created by the system
    case SYSTEM = 'system';

        // All assistants accessible to the user
    case ACCESSIBLE = 'accessible';
}
