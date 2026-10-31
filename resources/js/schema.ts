export type Field = string | string[];
export const schema: Record<
    string,
    { description: string; fields: Record<string, Field> }
> = {
    sites: {
        description: "Manage contact center locations and local time zones.",
        fields: { timezone: "timezone" },
    },
    departments: {
        description: "Organize departments within each site.",
        fields: { site_id: "sites" },
    },
    teams: {
        description: "Group agents into site teams and departments.",
        fields: { site_id: "sites", department_id: "departments?" },
    },
    skills: {
        description: "Define the skills your contact center needs.",
        fields: {},
    },
    // Kept in the schema so relation pickers (for example `extension_id` on
    // agents) can resolve extension choices; the screen itself is rendered by
    // Admin/Extensions.vue through the extensions branch of the admin route.
    extensions: {
        description:
            "Provision SIP endpoints with voicemail, recording and routing.",
        fields: { site_id: "sites", number: "text" },
    },
    "business-hours": {
        description:
            "Define weekly opening windows and holidays. ISO weekdays: 1 Monday through 7 Sunday. Omitted days are closed.",
        fields: {
            timezone: "timezone",
            schedule: "schedule",
            holidays: "holidays",
        },
    },
    "routing-profiles": {
        description:
            "Choose opening hours, routing strategy and overflow behavior.",
        fields: {
            business_hours_id: "business-hours",
            strategy: [
                "ring_all",
                "round_robin",
                "least_recent",
                "fewest_calls",
                "skill_based",
            ],
            ring_timeout: "number",
            max_wait: "number",
            overflow_action: ["hangup", "voicemail", "callback"],
        },
    },
    agents: {
        description:
            "Connect user accounts with site teams, extensions and skills.",
        fields: {
            site_id: "sites",
            user_id: "users",
            team_id: "teams?",
            extension_id: "extensions?",
            skills: "agent-skills",
        },
    },
    queues: {
        description:
            "Configure queue membership, required skills and routing profiles.",
        fields: {
            site_id: "sites",
            routing_profile_id: "routing-profiles",
            priority: "number",
            members: "members",
            skills: "queue-skills",
        },
    },
};
export const defaults: Record<string, unknown> = {
    timezone: "Africa/Accra",
    ring_timeout: 30,
    max_wait: 300,
    priority: 0,
    strategy: "round_robin",
    overflow_action: "hangup",
    schedule: [{ day: 1, opens: "09:00", closes: "17:00" }],
    holidays: [],
    skills: [],
    members: [],
};
export const label = (s: string) =>
    s.replaceAll(/[-_]/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
