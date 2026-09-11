import { MorphIcon, type MorphIconProps } from 'morphicons/react';
import {
    AlertCircle as AlertCircleIconNode,
    Archive as ArchiveIconNode,
    ArrowDown as ArrowDownIconNode,
    ArrowDownToLine as ArrowDownToLineIconNode,
    ArrowLeft as ArrowLeftIconNode,
    ArrowLeftToLine as ArrowLeftToLineIconNode,
    ArrowRight as ArrowRightIconNode,
    ArrowRightToLine as ArrowRightToLineIconNode,
    ArrowUp as ArrowUpIconNode,
    ArrowUpToLine as ArrowUpToLineIconNode,
    Bell as BellIconNode,
    Brain as BrainIconNode,
    CalendarDays as CalendarDaysIconNode,
    ChartNoAxesCombined as ChartNoAxesCombinedIconNode,
    ChartPie as ChartPieIconNode,
    Check as CheckIconNode,
    CheckCheck as CheckCheckIconNode,
    CheckCircle2 as CheckCircle2IconNode,
    ChevronDown as ChevronDownIconNode,
    ChevronLeft as ChevronLeftIconNode,
    ChevronRight as ChevronRightIconNode,
    ChevronUp as ChevronUpIconNode,
    ChevronsUpDown as ChevronsUpDownIconNode,
    Circle as CircleIconNode,
    CircleAlert as CircleAlertIconNode,
    CircleCheck as CircleCheckIconNode,
    CircleDashed as CircleDashedIconNode,
    CircleDot as CircleDotIconNode,
    CircleDotDashed as CircleDotDashedIconNode,
    ClipboardCheck as ClipboardCheckIconNode,
    Clock as ClockIconNode,
    Copy as CopyIconNode,
    CornerDownLeft as CornerDownLeftIconNode,
    CornerDownRight as CornerDownRightIconNode,
    Dot as DotIconNode,
    Download as DownloadIconNode,
    ExternalLink as ExternalLinkIconNode,
    FileImage as FileImageIconNode,
    FileText as FileTextIconNode,
    Funnel as FunnelIconNode,
    Globe as GlobeIconNode,
    GripVertical as GripVerticalIconNode,
    Heart as HeartIconNode,
    Home as HomeIconNode,
    Image as ImageIconNode,
    Inbox as InboxIconNode,
    Info as InfoIconNode,
    KeyRound as KeyRoundIconNode,
    Lightbulb as LightbulbIconNode,
    Link as LinkIconNode,
    Link2 as Link2IconNode,
    ListChecks as ListChecksIconNode,
    Loader as LoaderIconNode,
    LoaderCircle as LoaderCircleIconNode,
    Lock as LockIconNode,
    LogOut as LogOutIconNode,
    Mail as MailIconNode,
    Menu as MenuIconNode,
    MessageCircle as MessageCircleIconNode,
    MessageCircleQuestionMark as MessageCircleQuestionMarkIconNode,
    Mic as MicIconNode,
    Minus as MinusIconNode,
    Monitor as MonitorIconNode,
    MonitorX as MonitorXIconNode,
    Moon as MoonIconNode,
    MoreHorizontal as MoreHorizontalIconNode,
    MoreVertical as MoreVerticalIconNode,
    Package as PackageIconNode,
    PackageCheck as PackageCheckIconNode,
    PackagePlus as PackagePlusIconNode,
    Paintbrush as PaintbrushIconNode,
    Palette as PaletteIconNode,
    Paperclip as PaperclipIconNode,
    Pause as PauseIconNode,
    Pencil as PencilIconNode,
    PencilLine as PencilLineIconNode,
    Pipette as PipetteIconNode,
    Play as PlayIconNode,
    Plus as PlusIconNode,
    RectangleHorizontal as RectangleHorizontalIconNode,
    RefreshCw as RefreshCwIconNode,
    Rocket as RocketIconNode,
    RotateCcw as RotateCcwIconNode,
    Scaling as ScalingIconNode,
    Search as SearchIconNode,
    Settings as SettingsIconNode,
    Shield as ShieldIconNode,
    ShieldCheck as ShieldCheckIconNode,
    SkipForward as SkipForwardIconNode,
    Smartphone as SmartphoneIconNode,
    SquareArrowOutUpRight as SquareArrowOutUpRightIconNode,
    SquareLibrary as SquareLibraryIconNode,
    SquarePen as SquarePenIconNode,
    Star as StarIconNode,
    Sun as SunIconNode,
    Trash2 as Trash2IconNode,
    TriangleAlert as TriangleAlertIconNode,
    Truck as TruckIconNode,
    Upload as UploadIconNode,
    User as UserIconNode,
    UserCheck as UserCheckIconNode,
    UserRoundX as UserRoundXIconNode,
    Users as UsersIconNode,
    X as XIconNode,
    XCircle as XCircleIconNode,
    type IconInput,
} from 'lucide';
import type { ComponentType } from 'react';

export type LucideProps = MorphIconProps;
export type LucideIcon = ComponentType<LucideProps>;

function createIcon(icon: IconInput, displayName: string): LucideIcon {
    const Component = (props: LucideProps) => (
        <MorphIcon icon={icon} reducedMotion="user" {...props} />
    );

    Component.displayName = displayName;

    return Component;
}

export const AlertCircle = createIcon(AlertCircleIconNode, 'AlertCircle');
export const Archive = createIcon(ArchiveIconNode, 'Archive');
export const ArrowDown = createIcon(ArrowDownIconNode, 'ArrowDown');
export const ArrowDownToLine = createIcon(ArrowDownToLineIconNode, 'ArrowDownToLine');
export const ArrowLeft = createIcon(ArrowLeftIconNode, 'ArrowLeft');
export const ArrowLeftToLine = createIcon(ArrowLeftToLineIconNode, 'ArrowLeftToLine');
export const ArrowRight = createIcon(ArrowRightIconNode, 'ArrowRight');
export const ArrowRightToLine = createIcon(ArrowRightToLineIconNode, 'ArrowRightToLine');
export const ArrowUp = createIcon(ArrowUpIconNode, 'ArrowUp');
export const ArrowUpToLine = createIcon(ArrowUpToLineIconNode, 'ArrowUpToLine');
export const Bell = createIcon(BellIconNode, 'Bell');
export const Brain = createIcon(BrainIconNode, 'Brain');
export const CalendarDays = createIcon(CalendarDaysIconNode, 'CalendarDays');
export const ChartNoAxesCombined = createIcon(ChartNoAxesCombinedIconNode, 'ChartNoAxesCombined');
export const ChartPie = createIcon(ChartPieIconNode, 'ChartPie');
export const Check = createIcon(CheckIconNode, 'Check');
export const CheckCheck = createIcon(CheckCheckIconNode, 'CheckCheck');
export const CheckCircle2Icon = createIcon(CheckCircle2IconNode, 'CheckCircle2Icon');
export const ChevronDown = createIcon(ChevronDownIconNode, 'ChevronDown');
export const ChevronLeft = createIcon(ChevronLeftIconNode, 'ChevronLeft');
export const ChevronRight = createIcon(ChevronRightIconNode, 'ChevronRight');
export const ChevronUp = createIcon(ChevronUpIconNode, 'ChevronUp');
export const ChevronsUpDown = createIcon(ChevronsUpDownIconNode, 'ChevronsUpDown');
export const Circle = createIcon(CircleIconNode, 'Circle');
export const CircleAlert = createIcon(CircleAlertIconNode, 'CircleAlert');
export const CircleCheck = createIcon(CircleCheckIconNode, 'CircleCheck');
export const CircleDashed = createIcon(CircleDashedIconNode, 'CircleDashed');
export const CircleDot = createIcon(CircleDotIconNode, 'CircleDot');
export const CircleDotDashed = createIcon(CircleDotDashedIconNode, 'CircleDotDashed');
export const CircleDotIcon = createIcon(CircleDotIconNode, 'CircleDotIcon');
export const ClipboardCheck = createIcon(ClipboardCheckIconNode, 'ClipboardCheck');
export const Clock = createIcon(ClockIconNode, 'Clock');
export const Copy = createIcon(CopyIconNode, 'Copy');
export const CornerDownLeft = createIcon(CornerDownLeftIconNode, 'CornerDownLeft');
export const CornerDownRight = createIcon(CornerDownRightIconNode, 'CornerDownRight');
export const Dot = createIcon(DotIconNode, 'Dot');
export const Download = createIcon(DownloadIconNode, 'Download');
export const ExternalLink = createIcon(ExternalLinkIconNode, 'ExternalLink');
export const FileImage = createIcon(FileImageIconNode, 'FileImage');
export const FileText = createIcon(FileTextIconNode, 'FileText');
export const Funnel = createIcon(FunnelIconNode, 'Funnel');
export const Globe = createIcon(GlobeIconNode, 'Globe');
export const GripVertical = createIcon(GripVerticalIconNode, 'GripVertical');
export const Heart = createIcon(HeartIconNode, 'Heart');
export const Home = createIcon(HomeIconNode, 'Home');
export const ImageIcon = createIcon(ImageIconNode, 'ImageIcon');
export const Inbox = createIcon(InboxIconNode, 'Inbox');
export const Info = createIcon(InfoIconNode, 'Info');
export const KeyRound = createIcon(KeyRoundIconNode, 'KeyRound');
export const Lightbulb = createIcon(LightbulbIconNode, 'Lightbulb');
export const Link = createIcon(LinkIconNode, 'Link');
export const Link2 = createIcon(Link2IconNode, 'Link2');
export const ListChecks = createIcon(ListChecksIconNode, 'ListChecks');
export const Loader = createIcon(LoaderIconNode, 'Loader');
export const LoaderCircle = createIcon(LoaderCircleIconNode, 'LoaderCircle');
export const Lock = createIcon(LockIconNode, 'Lock');
export const LogOut = createIcon(LogOutIconNode, 'LogOut');
export const Mail = createIcon(MailIconNode, 'Mail');
export const Menu = createIcon(MenuIconNode, 'Menu');
export const MessageCircle = createIcon(MessageCircleIconNode, 'MessageCircle');
export const MessageCircleQuestionMark = createIcon(MessageCircleQuestionMarkIconNode, 'MessageCircleQuestionMark');
export const Mic = createIcon(MicIconNode, 'Mic');
export const Minus = createIcon(MinusIconNode, 'Minus');
export const Monitor = createIcon(MonitorIconNode, 'Monitor');
export const MonitorX = createIcon(MonitorXIconNode, 'MonitorX');
export const Moon = createIcon(MoonIconNode, 'Moon');
export const MoreHorizontal = createIcon(MoreHorizontalIconNode, 'MoreHorizontal');
export const MoreVertical = createIcon(MoreVerticalIconNode, 'MoreVertical');
export const Package = createIcon(PackageIconNode, 'Package');
export const PackageCheck = createIcon(PackageCheckIconNode, 'PackageCheck');
export const PackageCheckIcon = createIcon(PackageCheckIconNode, 'PackageCheckIcon');
export const PackagePlusIcon = createIcon(PackagePlusIconNode, 'PackagePlusIcon');
export const Paintbrush = createIcon(PaintbrushIconNode, 'Paintbrush');
export const Palette = createIcon(PaletteIconNode, 'Palette');
export const Paperclip = createIcon(PaperclipIconNode, 'Paperclip');
export const Pause = createIcon(PauseIconNode, 'Pause');
export const Pencil = createIcon(PencilIconNode, 'Pencil');
export const PencilLineIcon = createIcon(PencilLineIconNode, 'PencilLineIcon');
export const Pipette = createIcon(PipetteIconNode, 'Pipette');
export const Play = createIcon(PlayIconNode, 'Play');
export const Plus = createIcon(PlusIconNode, 'Plus');
export const RectangleHorizontal = createIcon(RectangleHorizontalIconNode, 'RectangleHorizontal');
export const RefreshCw = createIcon(RefreshCwIconNode, 'RefreshCw');
export const Rocket = createIcon(RocketIconNode, 'Rocket');
export const RotateCcw = createIcon(RotateCcwIconNode, 'RotateCcw');
export const Scaling = createIcon(ScalingIconNode, 'Scaling');
export const Search = createIcon(SearchIconNode, 'Search');
export const Settings = createIcon(SettingsIconNode, 'Settings');
export const Shield = createIcon(ShieldIconNode, 'Shield');
export const ShieldCheck = createIcon(ShieldCheckIconNode, 'ShieldCheck');
export const SkipForward = createIcon(SkipForwardIconNode, 'SkipForward');
export const Smartphone = createIcon(SmartphoneIconNode, 'Smartphone');
export const SquareArrowOutUpRight = createIcon(SquareArrowOutUpRightIconNode, 'SquareArrowOutUpRight');
export const SquareLibrary = createIcon(SquareLibraryIconNode, 'SquareLibrary');
export const SquarePen = createIcon(SquarePenIconNode, 'SquarePen');
export const Star = createIcon(StarIconNode, 'Star');
export const Sun = createIcon(SunIconNode, 'Sun');
export const Trash2 = createIcon(Trash2IconNode, 'Trash2');
export const TriangleAlert = createIcon(TriangleAlertIconNode, 'TriangleAlert');
export const Truck = createIcon(TruckIconNode, 'Truck');
export const Upload = createIcon(UploadIconNode, 'Upload');
export const User = createIcon(UserIconNode, 'User');
export const UserCheck = createIcon(UserCheckIconNode, 'UserCheck');
export const UserRoundX = createIcon(UserRoundXIconNode, 'UserRoundX');
export const Users = createIcon(UsersIconNode, 'Users');
export const X = createIcon(XIconNode, 'X');
export const XCircleIcon = createIcon(XCircleIconNode, 'XCircleIcon');
