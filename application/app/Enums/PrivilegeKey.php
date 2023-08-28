<?php

namespace App\Enums;

enum PrivilegeKey: string
{
    case AddRole = 'ADD_ROLE';
    case ViewRole = 'VIEW_ROLE';
    case EditRole = 'EDIT_ROLE';
    case DeleteRole = 'DELETE_ROLE';
    case AddUser = 'ADD_USER';
    case EditUser = 'EDIT_USER';
    case ViewUser = 'VIEW_USER';
    case ExportUser = 'EXPORT_USER';
    case ActivateUser = 'ACTIVATE_USER';
    case DeactivateUser = 'DEACTIVATE_USER';
    case ArchiveUser = 'ARCHIVE_USER';
    case EditUserWorktime = 'EDIT_USER_WORKTIME';
    case EditUserVacation = 'EDIT_USER_VACATION';
    case AddTag = 'ADD_TAG';
    case EditTag = 'EDIT_TAG';
    case DeleteTag = 'DELETE_TAG';
    case AddDepartment = 'ADD_DEPARTMENT';
    case EditDepartment = 'EDIT_DEPARTMENT';
    case DeleteDepartment = 'DELETE_DEPARTMENT';
    case ViewVendorDatabase = 'VIEW_VENDOR_DB';
    case EditVendorDatabase = 'EDIT_VENDOR_DB';
    case ViewGeneralPricelist = 'VIEW_GENERAL_PRICELIST';
    case ViewVendorTask = 'VIEW_VENDOR_TASK';
    case EditInstitution = 'EDIT_INSTITUTION';
    case EditInstitutionWorktime = 'EDIT_INSTITUTION_WORKTIME';
    case CreateProject = 'CREATE_PROJECT';
    case ManageProject = 'MANAGE_PROJECT';
    case ReceiveAndManageProject = 'RECEIVE_AND_MANAGE_PROJECT';
    case ViewPersonalProject = 'VIEW_PERSONAL_PROJECT';
    case ViewInstitutionProjectList = 'VIEW_INSTITUTION_PROJECT_LIST';
    case ViewInstitutionProjectDetail = 'VIEW_INSTITUTION_PROJECT_DETAIL';
    case ChangeClient = 'CHANGE_CLIENT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
