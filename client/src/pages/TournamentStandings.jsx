import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Button, Card, Col, Form, Input, Modal, Row, Select, Space, Statistic, Tabs, Tag, Typography } from 'antd';

import { deleteTournament, getStandings, searchPlayers, updateTournament } from '../api/api';
import { correctPlayerName } from '../api/importApi';

import { StandingsTable } from '../components';

export default function TournamentStandings() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [tournament, setTournament] = useState(null);
  const [loading, setLoading] = useState(false);
  const [correctionOpen, setCorrectionOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [submittingCorrection, setSubmittingCorrection] = useState(false);
  const [savingTournament, setSavingTournament] = useState(false);
  const [deletingTournament, setDeletingTournament] = useState(false);
  const [selectedPlayer, setSelectedPlayer] = useState(null);
  const [canonicalOptions, setCanonicalOptions] = useState([]);
  const [searchingCanonical, setSearchingCanonical] = useState(false);
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(null);
  const [form] = Form.useForm();
  const [editForm] = Form.useForm();

  useEffect(() => {
    if (id) loadStandings(parseInt(id));
  }, [id]);

  const loadStandings = async (tournamentId) => {
    setLoading(true);
    setError(null);

    try {
      const data = await getStandings(tournamentId);
      setTournament(data);
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to load tournament details');
    } finally {
      setLoading(false);
    }
  };

  const openCorrectionModal = (playerName) => {
    setSelectedPlayer(playerName);
    setCorrectionOpen(true);
    setCanonicalOptions([]);
    setMessage(null);
    setError(null);
    form.setFieldsValue({
      aliasName: playerName,
      canonicalPlayerId: undefined,
      canonicalName: ''
    });
  };

  const closeCorrectionModal = () => {
    setCorrectionOpen(false);
    setSelectedPlayer(null);
    setCanonicalOptions([]);
    form.resetFields();
  };

  const openEditModal = () => {
    setEditOpen(true);
    setMessage(null);
    setError(null);
    editForm.setFieldsValue({
      name: tournament?.name ?? '',
      age_category: tournament?.age_category ?? undefined,
      event_type: tournament?.event_type ?? undefined
    });
  };

  const closeEditModal = () => {
    setEditOpen(false);
    editForm.resetFields();
  };

  const handleCanonicalSearch = async (value) => {
    if (!value?.trim()) {
      setCanonicalOptions([]);
      return;
    }

    setSearchingCanonical(true);

    try {
      const filteredResults = await searchPlayers(value, {
        excludeAliasName: selectedPlayer ?? undefined
      });
      setCanonicalOptions(filteredResults);
    } catch {
      setCanonicalOptions([]);
    } finally {
      setSearchingCanonical(false);
    }
  };

  const swissColumns = [
    { title: 'Rank', dataIndex: 'rank', key: 'rank' },
    {
      title: 'Participant',
      key: 'player_name',
      render: (_, record) => (
        <div className="flex items-center gap-2">
          {record.player_id ? (
            <Link to={`/players/${record.player_id}`}>{record.display_name ?? record.player?.name}</Link>
          ) : (
            <span>{record.display_name ?? record.player?.name}</span>
          )}
          {record.qualified_for_top_cut && <Tag color="gold">Top Cut</Tag>}
        </div>
      )
    },
    { title: 'Match W-L-T', dataIndex: 'record', key: 'record' },
    { title: '1. Score', dataIndex: 'score', key: 'score' },
    { title: '2. TB', dataIndex: 'tb', key: 'tb' },
    { title: '3. Pts', dataIndex: 'total_points_scored', key: 'total_points_scored' },
    { title: '4. Buchholz', dataIndex: 'buchholz_score', key: 'buchholz_score' },
    { title: 'Pts Diff', dataIndex: 'points_diff', key: 'points_diff' },
    {
      title: 'Match History',
      key: 'match_history',
      render: (_, record) => (
        <div className="flex flex-wrap gap-1">
          {record.match_history?.map((result, index) => (
            <Tag key={`${record.id}-${index}`} color={result === 'W' ? 'green' : 'red'}>
              {result}
            </Tag>
          ))}
        </div>
      )
    },
    {
      title: 'Actions',
      key: 'actions',
      render: (_, record) => <Button onClick={() => openCorrectionModal(record.alias_name ?? record.display_name ?? record.player?.name)}>Name Correction</Button>
    }
  ];

  const topCutColumns = [
    { title: 'Placement', dataIndex: 'placement', key: 'placement' },
    { title: 'Finish', dataIndex: 'placement_label', key: 'placement_label' },
    {
      title: 'Player',
      key: 'player_name',
      render: (_, record) =>
        record.player?.id ? (
          <Link to={`/players/${record.player.id}`}>{record.display_name ?? record.player?.name}</Link>
        ) : (
          record.display_name ?? record.player?.name
        )
    },
    { title: 'Points', dataIndex: 'points_awarded', key: 'points_awarded' },
    {
      title: 'Actions',
      key: 'actions',
      render: (_, record) => <Button onClick={() => openCorrectionModal(record.alias_name ?? record.display_name ?? record.player?.name)}>Name Correction</Button>
    }
  ];

  const summary = tournament?.summary ?? {};

  const summaryValue = (entry, fallback) => entry?.name ?? fallback;

  const standingTabs = [
    ...(tournament?.has_top_cut ? [{
      key: 'top-cut',
      label: 'Top Cut Standings',
      children: <StandingsTable standings={tournament?.top_cut_standings ?? []} columns={topCutColumns} />
    }] : []),
    ...(tournament?.has_swiss_phase ? [{
      key: 'group-stage',
      label: 'Group Stage Standings',
      children: (
        <StandingsTable
          standings={tournament?.swiss_standings ?? []}
          columns={swissColumns}
          rowClassName={(record) => (record.qualified_for_top_cut ? 'top-cut-qualified-row' : '')}
        />
      )
    }] : [])
  ];

  const submitCorrection = async () => {
    setSubmittingCorrection(true);
    setMessage(null);
    setError(null);

    try {
      const values = await form.validateFields();
      const selectedCanonical = canonicalOptions.find((option) => option.id === values.canonicalPlayerId);
      const canonicalName = selectedCanonical?.name ?? values.canonicalName;

      await correctPlayerName({
        aliasName: values.aliasName,
        canonicalName
      });

      setMessage(`Mapped "${values.aliasName}" to "${canonicalName}" and rebuilt leaderboard.`);
      closeCorrectionModal();
      if (id) {
        await loadStandings(parseInt(id));
      }
    } catch (err) {
      if (err.errorFields) {
        return;
      }

      setError(err.response?.data?.message ?? 'Failed to apply name correction');
    } finally {
      setSubmittingCorrection(false);
    }
  };

  const submitTournamentUpdate = async () => {
    setSavingTournament(true);
    setMessage(null);
    setError(null);

    try {
      const values = await editForm.validateFields();
      await updateTournament(id, values);
      setMessage('Tournament info updated.');
      closeEditModal();
      if (id) {
        await loadStandings(parseInt(id));
      }
    } catch (err) {
      if (err.errorFields) {
        return;
      }

      setError(err.response?.data?.message ?? 'Failed to update tournament info');
    } finally {
      setSavingTournament(false);
    }
  };

  const handleDeleteTournament = () => {
    Modal.confirm({
      title: 'Delete Tournament',
      content: `Delete "${tournament?.name}"? This will remove its standings and leaderboard points.`,
      okText: deletingTournament ? 'Deleting...' : 'Delete Tournament',
      okButtonProps: { danger: true, loading: deletingTournament },
      cancelText: 'Cancel',
      onOk: async () => {
        setDeletingTournament(true);
        setMessage(null);
        setError(null);

        try {
          await deleteTournament(id);
          navigate('/tournaments');
        } catch (err) {
          setError(err.response?.data?.message ?? 'Failed to delete tournament');
        } finally {
          setDeletingTournament(false);
        }
      }
    });
  };

  const ageCategoryLabel = tournament?.age_category === 'junior'
    ? 'Junior Cat'
    : tournament?.age_category === 'open'
      ? 'Open Cat'
      : 'Uncategorized';

  const eventTypeLabel = tournament?.event_type === 'casual'
    ? 'Casual'
    : tournament?.event_type === 'tournament'
      ? 'Tournament'
      : 'Unspecified';

  return (
    <div className="container flex flex-col gap-6">
      <Card
        loading={loading}
        title={tournament?.name ?? 'Tournament Details'}
        extra={(
          <Space>
            <Typography.Text>{tournament?.date}</Typography.Text>
            <Button onClick={openEditModal} disabled={!tournament}>Edit Tournament</Button>
            <Button danger onClick={handleDeleteTournament} disabled={!tournament || deletingTournament}>
              Delete Tournament
            </Button>
          </Space>
        )}
      >
        <Row gutter={[16, 16]}>
          <Col xs={24} md={12} lg={6}>
            <Statistic title="Champ" value={summaryValue(summary.champ, 'Unavailable')} />
          </Col>
          <Col xs={24} md={12} lg={6}>
            <Statistic title="Finisher" value={summaryValue(summary.finisher, 'Unavailable')} />
          </Col>
          <Col xs={24} md={12} lg={6}>
            <Statistic title="Swiss King" value={summaryValue(summary.swiss_king, 'Unavailable')} />
          </Col>
          <Col xs={24} md={12} lg={6}>
            <Statistic title="Birdie King" value={summaryValue(summary.birdie_king, 'None')} />
          </Col>
        </Row>

        <div className="mt-4 flex gap-2 flex-wrap">
          <Tag>{ageCategoryLabel}</Tag>
          <Tag color="blue">{eventTypeLabel}</Tag>
        </div>

        {tournament?.challonge_url && (
          <Typography.Paragraph className="mt-4">
            Challonge: <a href={tournament.challonge_url} target="_blank" rel="noreferrer">{tournament.challonge_slug}</a>
          </Typography.Paragraph>
        )}

        {message && <Typography.Paragraph type="success">{message}</Typography.Paragraph>}
        {error && <Typography.Paragraph type="danger">{error}</Typography.Paragraph>}
      </Card>

      <Card title="Standings">
        <Tabs
          defaultActiveKey={tournament?.has_top_cut ? 'top-cut' : 'group-stage'}
          items={standingTabs}
        />
      </Card>

      <Modal
        title={`Name Correction${selectedPlayer ? `: ${selectedPlayer}` : ''}`}
        open={correctionOpen}
        onCancel={closeCorrectionModal}
        onOk={submitCorrection}
        okText={submittingCorrection ? 'Applying...' : 'Apply Correction'}
        confirmLoading={submittingCorrection}
      >
        <Form form={form} layout="vertical">
          <Form.Item label="Alias Name" name="aliasName" rules={[{ required: true, message: 'Alias name is required' }]}>
            <Input readOnly />
          </Form.Item>
          <Form.Item
            label="Known Players / Aliases"
            name="canonicalPlayerId"
          >
            <Select
              showSearch
              allowClear
              filterOption={false}
              placeholder="Search for an existing player or alias"
              onSearch={handleCanonicalSearch}
              loading={searchingCanonical}
              options={canonicalOptions.map((option) => ({
                value: option.id,
                label: option.aliases?.length
                  ? `${option.name} (${option.aliases.join(', ')})`
                  : option.name
              }))}
            />
          </Form.Item>
          <Form.Item
            label="Canonical Name"
            name="canonicalName"
            rules={[
              {
                validator: async (_, value) => {
                  const selectedId = form.getFieldValue('canonicalPlayerId');

                  if (selectedId || (value && value.trim())) {
                    return;
                  }

                  throw new Error('Select a known player/alias or type a canonical name');
                }
              }
            ]}
          >
            <Input placeholder="If not found above, type the canonical player name" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title="Edit Tournament Info"
        open={editOpen}
        onCancel={closeEditModal}
        onOk={submitTournamentUpdate}
        okText={savingTournament ? 'Saving...' : 'Save Changes'}
        confirmLoading={savingTournament}
      >
        <Form form={editForm} layout="vertical">
          <Form.Item
            label="Tournament Name"
            name="name"
            rules={[{ required: true, message: 'Tournament name is required' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item label="Category" name="age_category">
            <Select
              allowClear
              placeholder="Select category"
              options={[
                { value: 'junior', label: 'Junior Cat' },
                { value: 'open', label: 'Open Cat' }
              ]}
            />
          </Form.Item>
          <Form.Item label="Event Type" name="event_type">
            <Select
              allowClear
              placeholder="Select event type"
              options={[
                { value: 'casual', label: 'Casual' },
                { value: 'tournament', label: 'Tournament' }
              ]}
            />
          </Form.Item>
        </Form>
      </Modal>
    </div>
  );
}
