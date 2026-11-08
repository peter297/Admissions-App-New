import { Link } from "@inertiajs/react";
import {
    BookOpen,
    BusFront,
    FileText,
    FolderCog,
    History,
    LayoutGrid,
    Search,
    Settings,
    Upload,
    UserPlus,
    UsersRound,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavFooter } from "@/components/nav-footer";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { useCan } from "@/hooks/use-can";
import { dashboard } from "@/routes";
import type { NavItem } from "@/types";

// Every item names the permission needed to open its page. The permission is REQUIRED, so an item
// cannot be added without one: it would otherwise be shown to everybody.
type AppNavItem = NavItem & { permission: string };

const allNavItems: AppNavItem[] = [
    {
        title: "Dashboard",
        href: dashboard(),
        icon: LayoutGrid,
        permission: "dashboard.view",
    },
    {
        title: "Find a learner",
        href: "/learners",
        icon: Search,
        permission: "learners.view",
    },
    {
        title: "Active classes",
        href: "/classes",
        icon: BookOpen,
        permission: "classes.view",
    },
    {
        title: "Transport",
        href: "/transport",
        icon: BusFront,
        permission: "transport.view",
    },
    {
        title: "Academic Years",
        href: "/academic-years",
        icon: FolderCog,
        permission: "academic-years.manage",
    },
    {
        title: "Alumni",
        href: "/alumni",
        icon: UsersRound,
        permission: "alumni.view",
    },
    {
        title: "Admissions",
        href: "/admissions",
        icon: UserPlus,
        permission: "admissions.view",
    },
    {
        title: "Historical data",
        href: "/history",
        icon: History,
        permission: "history.view",
    },
    {
        title: "Reports",
        href: "/reports",
        icon: FileText,
        permission: "reports.view",
    },

    {
        title: "Import learners",
        href: "/import",
        icon: Upload,
        permission: "learners.import",
    },
    {

        title: "School settings",
        href: "/settings/classes",
        icon: Settings,
        permission: "settings.manage",
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const can = useCan();

    // Only the items this user may open. NavMain never sees the others.
    const mainNavItems: NavItem[] = allNavItems
        .filter((item) => can(item.permission))
        .map(({ title, href, icon }) => ({ title, href, icon }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
