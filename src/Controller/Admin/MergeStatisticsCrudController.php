<?php

declare(strict_types=1);

namespace CreditMergeBundle\Controller\Admin;

use CreditBundle\Entity\Account;
use CreditMergeBundle\Entity\MergeStatistics;
use CreditMergeBundle\Enum\TimeWindowStrategy;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * 合并统计历史管理控制器
 * 用于管理积分合并操作的统计数据和分析报告.
 *
 * @extends AbstractCrudController<MergeStatistics>
 */
#[AdminCrud(routePath: '/credit-merge/statistics', routeName: 'credit_merge_statistics')]
#[Autoconfigure(public: true)]
final class MergeStatisticsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MergeStatistics::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('合并统计')
            ->setEntityLabelInPlural('合并统计管理')
            ->setPageTitle('index', '合并统计列表')
            ->setPageTitle('detail', '合并统计详情')
            ->setPageTitle('new', '新建合并统计')
            ->setPageTitle('edit', '编辑合并统计')
            ->setHelp('index', '查看和管理积分合并操作的统计数据，分析合并效率和潜在收益')
            ->setDefaultSort(['statisticsTime' => 'DESC', 'createdAt' => 'DESC'])
            ->setSearchFields(['account.name', 'timeWindowStrategy.value', 'minAmountThreshold'])
            ->setPaginatorPageSize(30)
            ->setEntityPermission('ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
            ->setHelp('统计记录的唯一标识符')
        ;

        yield AssociationField::new('account', '账户')
            ->setRequired(true)
            ->setHelp('合并统计关联的积分账户')
            ->formatValue(static function ($value): string {
                if ($value instanceof Account) {
                    return $value->getName();
                }

                return 'N/A';
            })
        ;

        yield DateTimeField::new('statisticsTime', '统计时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setRequired(true)
            ->setHelp('生成统计数据的时间点')
        ;

        $timeWindowStrategyField = EnumField::new('timeWindowStrategy', '时间窗口策略');
        $timeWindowStrategyField->setEnumCases(TimeWindowStrategy::cases());
        yield $timeWindowStrategyField
            ->setRequired(true)
            ->setHelp('合并时使用的时间窗口分组策略（按天、按周、按月或全部合并）')
        ;

        yield MoneyField::new('minAmountThreshold', '最小金额阈值')
            ->setCurrency('CNY')
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setHelp('合并操作使用的最小金额阈值，低于此值的记录被视为小额记录')
        ;

        yield IntegerField::new('totalSmallRecords', '小额记录总数')
            ->setRequired(true)
            ->setHelp('符合小额条件的积分记录总数量')
        ;

        yield MoneyField::new('totalSmallAmount', '小额积分总额')
            ->setCurrency('CNY')
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setHelp('所有小额积分记录的总金额')
        ;

        yield IntegerField::new('mergeableRecords', '可合并记录数')
            ->setRequired(true)
            ->setHelp('在当前策略下可以进行合并的记录数量')
        ;

        yield IntegerField::new('potentialRecordReduction', '潜在减少记录数')
            ->setRequired(true)
            ->setHelp('执行合并后可以减少的记录数量')
        ;

        yield PercentField::new('mergeEfficiency', '合并效率')
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setHelp('合并操作的效率百分比，表示记录数量的减少比例')
        ;

        yield MoneyField::new('averageAmount', '平均记录金额')
            ->setCurrency('CNY')
            ->setNumDecimals(2)
            ->setRequired(true)
            ->setHelp('小额记录的平均金额')
        ;

        yield IntegerField::new('timeWindowGroups', '时间窗口分组数')
            ->setRequired(true)
            ->setHelp('按时间窗口策略划分的分组数量')
        ;

        yield ArrayField::new('groupStats', '分组统计详情')
            ->onlyOnDetail()
            ->setHelp('按时间窗口分组的详细统计信息（JSON格式）')
        ;

        yield ArrayField::new('context', '统计上下文')
            ->onlyOnDetail()
            ->setHelp('统计操作的上下文信息，包含配置和环境参数（JSON格式）')
        ;

        yield DateTimeField::new('createdAt', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
            ->setHelp('统计记录的创建时间')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            // 保持默认的动作排序，避免配置复杂性
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('account', '账户'))
            ->add(ChoiceFilter::new('timeWindowStrategy', '时间窗口策略')
                ->setChoices([
                    '按天' => TimeWindowStrategy::DAY->value,
                    '按周' => TimeWindowStrategy::WEEK->value,
                    '按月' => TimeWindowStrategy::MONTH->value,
                    '全部合并' => TimeWindowStrategy::ALL->value,
                ])
            )
            ->add(NumericFilter::new('minAmountThreshold', '最小金额阈值'))
            ->add(NumericFilter::new('totalSmallRecords', '小额记录总数'))
            ->add(NumericFilter::new('totalSmallAmount', '小额积分总额'))
            ->add(NumericFilter::new('mergeableRecords', '可合并记录数'))
            ->add(NumericFilter::new('potentialRecordReduction', '潜在减少记录数'))
            ->add(NumericFilter::new('mergeEfficiency', '合并效率'))
            ->add(NumericFilter::new('averageAmount', '平均记录金额'))
            ->add(NumericFilter::new('timeWindowGroups', '时间窗口分组数'))
            ->add(DateTimeFilter::new('statisticsTime', '统计时间'))
            ->add(DateTimeFilter::new('createdAt', '创建时间'))
        ;
    }
}
