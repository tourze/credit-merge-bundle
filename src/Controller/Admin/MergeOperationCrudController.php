<?php

declare(strict_types=1);

namespace CreditMergeBundle\Controller\Admin;

use CreditMergeBundle\Entity\MergeOperation;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

#[AdminCrud(routePath: '/credit-merge/merge-operation', routeName: 'credit_merge_merge_operation')]
final class MergeOperationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MergeOperation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('积分合并操作')
            ->setEntityLabelInPlural('积分合并操作记录')
            ->setPageTitle('index', '积分合并操作记录列表')
            ->setPageTitle('new', '新建合并操作记录')
            ->setPageTitle('edit', '编辑合并操作记录')
            ->setPageTitle('detail', '合并操作记录详情')
            ->setHelp('index', '管理积分合并操作记录，查看每次合并操作的详细信息和统计数据')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['status', 'resultMessage'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield AssociationField::new('account', '积分账户')
            ->setRequired(true)
            ->setHelp('关联的积分账户')
        ;

        yield DateTimeField::new('operationTime', '操作时间')
            ->setRequired(true)
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('执行合并操作的时间')
        ;

        yield ChoiceField::new('timeWindowStrategy', '时间窗口策略')
            ->setChoices([
                '按天' => 'day',
                '按周' => 'week',
                '按月' => 'month',
                '全部合并' => 'all',
            ])
            ->setRequired(true)
            ->setHelp('使用的时间窗口合并策略')
        ;

        yield MoneyField::new('minAmountThreshold', '最小金额阈值')
            ->setCurrency('CNY')
            ->setRequired(true)
            ->setHelp('合并操作的最小金额阈值')
        ;

        yield IntegerField::new('recordsCountBefore', '合并前记录数')
            ->setRequired(true)
            ->setHelp('执行合并前的记录总数')
        ;

        yield IntegerField::new('recordsCountAfter', '合并后记录数')
            ->setRequired(true)
            ->setHelp('执行合并后的记录总数')
        ;

        yield IntegerField::new('mergedRecordsCount', '已合并记录数')
            ->setRequired(true)
            ->setHelp('实际合并的记录数量')
        ;

        yield MoneyField::new('totalAmount', '涉及总金额')
            ->setCurrency('CNY')
            ->setRequired(true)
            ->setHelp('合并操作涉及的积分总额')
        ;

        yield IntegerField::new('batchSize', '批次大小')
            ->setRequired(true)
            ->setHelp('每次处理的批次大小')
        ;

        yield BooleanField::new('isDryRun', '模拟运行')
            ->setRequired(true)
            ->setHelp('是否为模拟运行（不实际执行合并）')
        ;

        yield ChoiceField::new('status', '操作状态')
            ->setChoices([
                '待处理' => 'pending',
                '成功' => 'success',
                '失败' => 'failed',
                '部分成功' => 'partial',
            ])
            ->setRequired(true)
            ->setHelp('操作执行结果状态')
        ;

        yield TextareaField::new('resultMessage', '结果信息')
            ->setNumOfRows(3)
            ->setHelp('操作结果详情或错误信息')
        ;

        yield TextareaField::new('executionTime', '执行耗时(秒)')
            ->setHelp('操作执行耗时，单位为秒')
        ;

        yield DateTimeField::new('createdAt', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT) // 通常操作记录不允许手动创建或编辑
            // 删除操作默认存在于detail页面，不需要在index页面排序
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('account', '积分账户'))
            ->add(ChoiceFilter::new('timeWindowStrategy', '时间窗口策略')
                ->setChoices([
                    '按天' => 'day',
                    '按周' => 'week',
                    '按月' => 'month',
                    '全部合并' => 'all',
                ])
            )
            ->add(ChoiceFilter::new('status', '操作状态')
                ->setChoices([
                    '待处理' => 'pending',
                    '成功' => 'success',
                    '失败' => 'failed',
                    '部分成功' => 'partial',
                ])
            )
            ->add(BooleanFilter::new('isDryRun', '模拟运行'))
            ->add(DateTimeFilter::new('operationTime', '操作时间'))
            ->add(DateTimeFilter::new('createdAt', '创建时间'))
        ;
    }
}
